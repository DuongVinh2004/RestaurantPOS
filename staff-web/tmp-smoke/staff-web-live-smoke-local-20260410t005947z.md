# Staff-Web Live Smoke

- Decision: `block`
- Target: `local`
- Mode: `read-only`
- API: `http://127.0.0.1:8000/api/v1`
- Preview: `preview`
- Preview status: `not-configured`

## Steps

| Step | Status | Detail |
| --- | --- | --- |
| backend health | PASS | ok |
| login | PASS | staff_api_key_id=13 \| access=ready, branch=ready, shift=action_required |
| session me | PASS | user=uat.staff \| access=ready, branch=ready, shift=action_required |
| session refresh | PASS | expires_at=2026-04-10T12:59:46+00:00 \| access=ready, branch=ready, shift=action_required |
| board refresh | PASS | items=8 |
| board changes | PASS | current_version=266, has_changes=false, stale_cursor=false |
| waiting list | PASS | ok |
| waiting changes | PASS | current_version=62, has_changes=false, stale_cursor=false |
| reservation lookup | PASS | ok |
| reservation orders | FAIL | 404 req=1556d369-7517-4727-81a0-f38057a9c425 No query results for model [App\Models\Reservation] 2 |
| menu items | PASS | items=3 |
| order create | SKIP | no existing order and order-create gate disabled |
| order add-item | SKIP | no order available |
| settlement finalize | SKIP | no order available for finalize |
| refund preview | FAIL | 404 req=3cb2920b-5f5c-4c88-b48a-e37d098a58cf No query results for model [App\Models\Reservation] 4 |
| refund execute | SKIP | no reservation available for refund |
| cashier current | PASS | ok |
| cashier open | SKIP | no open shift and cashier-open gate disabled |
| cashier close | SKIP | no cashier shift available |
| conversations list | PASS | ok |
| conversation detail | FAIL | 500 req=5d6a1965-84f7-481f-a269-f1673a3feeff Database query error. |
| conversation ai assist | SKIP | ai assist payload not present in conversation detail |
