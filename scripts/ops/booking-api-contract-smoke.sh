#!/usr/bin/env bash
set -euo pipefail

echo "[booking-api-contract-smoke] Verify canonical aliases are wired:"
echo "- POST /api/v1/staff/orders/{order_id}/bill-snapshot"
echo "- GET  /api/v1/staff/orders/{order_id}/settlement-preview"
echo "- POST /api/v1/staff/orders/{order_id}/settlement/finalize"
echo "- GET  /api/v1/staff/reservations/{reservation_id}/refund-preview"
echo "- POST /api/v1/staff/reservations/{reservation_id}/voucher/release"
echo "- POST /api/v1/staff/reservations/{reservation_id}/loyalty/release"
