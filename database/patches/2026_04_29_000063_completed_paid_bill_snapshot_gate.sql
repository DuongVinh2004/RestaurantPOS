-- Completed paid on-spot service reservations must carry the immutable bill snapshot.
-- This is a release gate rather than a CHECK constraint because the invariant depends
-- on payment and order rows, and legacy non-payment Completed states must remain importable.

SET @completed_paid_missing_bill_snapshot_count := (
  SELECT COUNT(*)
    FROM `reservations` r
   WHERE r.`status` = 'Completed'
     AND (
       r.`final_bill_amount` IS NULL
       OR r.`billed_at` IS NULL
       OR TRIM(COALESCE(r.`bill_currency`, '')) = ''
     )
     AND EXISTS (
       SELECT 1
         FROM `reservation_orders` ro
        WHERE ro.`reservation_id` = r.`reservation_id`
          AND ro.`order_type` = 'OnSpot'
          AND ro.`status` = 'Completed'
     )
     AND EXISTS (
       SELECT 1
         FROM `payments` p
        WHERE p.`reservation_id` = r.`reservation_id`
          AND p.`payment_type` IN ('Deposit', 'Final')
          AND p.`status` IN ('Success', 'Partial')
     )
);

SET @stmt := IF(
  @completed_paid_missing_bill_snapshot_count = 0,
  'SELECT "reservations.completed_paid_bill_snapshot:ok"',
  'SELECT * FROM __drifted_completed_paid_reservations_missing_bill_snapshot__'
);
PREPARE completed_paid_bill_snapshot_stmt FROM @stmt;
EXECUTE completed_paid_bill_snapshot_stmt;
DEALLOCATE PREPARE completed_paid_bill_snapshot_stmt;
