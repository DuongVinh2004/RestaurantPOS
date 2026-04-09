-- Optimize table_holds & table_hold_details for:
-- - expire holds by status + expire_at
-- - conflict checks by start_time + status + expire_at
-- - fetching holds by session_id + start_time
-- - excluding holds by table_id join

-- 1) table_holds
ALTER TABLE table_holds
  ADD INDEX idx_table_holds__status__expire_at__start_time (hold_status, expire_at, start_time);

ALTER TABLE table_holds
  ADD INDEX idx_table_holds__session_id__start_time__created_at (session_id, start_time, created_at);

-- 2) table_hold_details
ALTER TABLE table_hold_details
  ADD INDEX idx_table_hold_details__table_id__hold_id (table_id, hold_id);

-- (Optional) nếu bạn hay query theo hold_id lấy tables mà table_hold_details chưa có PK/INDEX tốt:
-- ALTER TABLE table_hold_details
--   ADD INDEX idx_table_hold_details__hold_id__table_id (hold_id, table_id);
