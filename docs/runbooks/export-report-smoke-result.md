# CSV/Report Export E2E Smoke Results

- **Overall Status**: PASS

| Step | Status | Detail |
|---|---|---|
| Staff Auth | PASS | Logged in staff successfully. |
| Staff Financial Accounting Export (CSV) | PASS | Export succeeded. Size: 5685 bytes. Columns found: [reservation_id, reservation_code, reservation_status, deposit_status, customer_user_id, customer_name, customer_email, customer_phone, start_time, end_time, bill_currency, payment_count, refund_count, captured_amount, refunded_amount, net_paid_amount, deposit_required_amount, deposit_recorded_paid_amount, deposit_computed_net_amount, deposit_sync_gap_amount, final_bill_amount, bill_outstanding_amount, bill_overpaid_amount, deposit_captured_amount, deposit_refunded_amount, final_captured_amount, final_refunded_amount, last_payment_activity_at, last_refund_at, payment_currency, has_mixed_payment_currencies, has_discrepancy, has_deposit_sync_gap, has_over_refund, has_bill_outstanding, has_bill_overpaid, discrepancy_reasons, is_fully_settled, invoice_number, invoice_status, invoice_subtotal_amount, invoice_discount_amount, invoice_total_amount, invoice_currency, invoice_tax_code, invoice_tax_name, invoice_tax_rate_percentage, invoice_taxable_amount, invoice_tax_amount, invoice_prices_include_tax, invoice_issued_at, invoice_seller_name, invoice_seller_tax_id, invoice_seller_address]. |
| Staff Financial Accounting Export (CSV) - Unauthorized Block | PASS | Correctly rejected unauthorized request with status 401. |
| Staff Financial Accounting Export (JSON) | PASS | Export succeeded in JSON format. Records count: 16. |
| Staff Financial Accounting Export (JSON) - Unauthorized Block | PASS | Correctly rejected unauthorized request with status 401. |
| Staff Financial Reconciliation Export (CSV) | PASS | Export succeeded. Size: 5099 bytes. Columns found: [reservation_id, reservation_code, reservation_status, deposit_status, customer_user_id, customer_name, customer_email, customer_phone, start_time, end_time, bill_currency, payment_count, refund_count, captured_amount, refunded_amount, net_paid_amount, deposit_required_amount, deposit_recorded_paid_amount, deposit_computed_net_amount, deposit_sync_gap_amount, final_bill_amount, bill_outstanding_amount, bill_overpaid_amount, deposit_captured_amount, deposit_refunded_amount, final_captured_amount, final_refunded_amount, last_payment_activity_at, last_refund_at, payment_currency, has_mixed_payment_currencies, has_discrepancy, has_deposit_sync_gap, has_over_refund, has_bill_outstanding, has_bill_overpaid, discrepancy_reasons, is_fully_settled]. |
| Staff Financial Reconciliation Export (CSV) - Unauthorized Block | PASS | Correctly rejected unauthorized request with status 401. |
| Staff Financial Reconciliation Export (JSON) | PASS | Export succeeded in JSON format. Records count: 16. |
| Staff Financial Reconciliation Export (JSON) - Unauthorized Block | PASS | Correctly rejected unauthorized request with status 401. |
| Admin Menu Categories Export (CSV) | PASS | Export succeeded. Size: 543 bytes. Columns found: [name, description, sort_order, is_deleted]. |
| Admin Menu Categories Export (CSV) - Unauthorized Block | PASS | Correctly rejected unauthorized request with status 401. |
| Admin Menu Items Export (CSV) | PASS | Export succeeded. Size: 7527 bytes. Columns found: [code, name, category_name, description, img_url, is_available, is_preorder_enabled, preorder_quota_per_day, preorder_cutoff_minutes]. |
| Admin Menu Items Export (CSV) - Unauthorized Block | PASS | Correctly rejected unauthorized request with status 401. |
| Admin Restaurant Tables Export (CSV) | PASS | Export succeeded. Size: 6730 bytes. Columns found: [branch_code, table_code, template_code, zone, pos_x, pos_y, status, description, price, is_deleted]. |
| Admin Restaurant Tables Export (CSV) - Unauthorized Block | PASS | Correctly rejected unauthorized request with status 401. |
