#!/usr/bin/env bash

set -euo pipefail

EVIDENCE_DIR="storage/app/booking_release/staging_rehearsal_evidence"
mkdir -p "${EVIDENCE_DIR}"

echo "============================================================"
echo " RestaurantPOS Live Staging Verification Script"
echo "============================================================"
echo ""

# 1. Check System Binaries
echo "[1] Checking system binaries..."
MISSING_BINARIES=0
for bin in php composer node npm mysql redis-cli nginx supervisorctl; do
    if ! command -v "$bin" &> /dev/null; then
        echo "❌ Missing: $bin"
        MISSING_BINARIES=1
    else
        echo "✅ Found: $bin ($(command -v "$bin"))"
    fi
done

if [ "$MISSING_BINARIES" -eq 1 ]; then
    echo ""
    echo "🚨 ERROR: Missing required binaries. Ensure you are on a real Ubuntu VPS with all stack components installed."
    exit 1
fi
echo ""

# 2. Check Backend Quality
echo "[2] Running backend quality checks..."
echo "Running composer validate..."
composer validate || { echo "❌ composer validate failed"; exit 1; }

echo "Running composer audit..."
composer audit || { echo "❌ composer audit failed"; exit 1; }

echo "Running pint..."
vendor/bin/pint --test || { echo "❌ pint failed"; exit 1; }

echo "Running phpstan..."
vendor/bin/phpstan analyse --memory-limit=1G --no-progress || { echo "❌ phpstan failed"; exit 1; }

echo "Running tests..."
php artisan test || { echo "❌ tests failed"; exit 1; }
echo "✅ Backend quality checks passed."
echo ""

# 3. Check System Daemons
echo "[3] Checking System Daemons..."
echo "Checking supervisor status..."
sudo supervisorctl status > "${EVIDENCE_DIR}/supervisor_status.txt" || { echo "❌ supervisorctl status failed"; exit 1; }
cat "${EVIDENCE_DIR}/supervisor_status.txt"
echo "✅ Supervisor check complete."
echo ""

# 4. Check Health & Readiness Gates
echo "[4] Running Booking Release Gates..."

echo "Running doctor..."
php artisan booking:doctor --json || { echo "❌ booking:doctor failed"; exit 1; }

echo "Running deploy-check..."
php artisan booking:deploy-check --mode=preflight --strict --json || { echo "❌ booking:deploy-check failed"; exit 1; }

echo "Running release-manifest..."
php artisan booking:release-manifest --json || { echo "❌ booking:release-manifest failed"; exit 1; }

echo "Running launch-readiness..."
php artisan booking:launch-readiness --target=staging --json || { echo "❌ booking:launch-readiness failed"; exit 1; }
echo "✅ All Booking Release Gates passed."
echo ""

# 5. Ops Logs Extraction
echo "[5] Extracting Logs..."
tail -n 50 storage/logs/laravel.log > "${EVIDENCE_DIR}/laravel_tail.log" || true
tail -n 50 storage/logs/queue-worker.log > "${EVIDENCE_DIR}/queue_worker_tail.log" || true
tail -n 50 storage/logs/scheduler-worker.log > "${EVIDENCE_DIR}/scheduler_worker_tail.log" || true
echo "✅ Logs extracted to ${EVIDENCE_DIR}"
echo ""

echo "============================================================"
echo " 🎉 STAGING VERIFICATION SUCCESSFUL"
echo "============================================================"
echo "Evidence artifacts have been safely stored in ${EVIDENCE_DIR}"
