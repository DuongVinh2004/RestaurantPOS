#!/usr/bin/env bash
set -euo pipefail

# Print usage instructions
usage() {
    echo "Usage: $0 [options]"
    echo "Options:"
    echo "  --dry-run        Execute the rehearsal runner as a dry-run without writing output files"
    echo "  --local-only     Verify local checks and ignore remote/external S3 and third-party probes"
    echo "  --staging        Simulate realistic serious-staging readiness with strict preflights"
    echo "  --metadata-only  Inspect release-manifest and template existence without active database runs"
    echo "  -h, --help       Show this help message"
    exit 0
}

# Parse options
DRY_RUN=false
LOCAL_ONLY=false
STAGING_MODE=false
METADATA_ONLY=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --local-only)
            LOCAL_ONLY=true
            shift
            ;;
        --staging)
            STAGING_MODE=true
            shift
            ;;
        --metadata-only)
            METADATA_ONLY=true
            shift
            ;;
        -h|--help)
            usage
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

echo "========================================================="
echo "       RestaurantPOS Staging Rehearsal Runner            "
echo "========================================================="

# 1. Environment Verification
APP_ENV="${APP_ENV:-local}"
if [ "$APP_ENV" = "production" ]; then
    echo "[ERROR] The Rehearsal Runner cannot be run in production environment!"
    exit 1
fi

BRANCH_NAME=$(git branch --show-current 2>/dev/null || echo "detached")
COMMIT_HASH=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")

echo "[INFO] Working Branch: ${BRANCH_NAME}"
echo "[INFO] Latest Commit : ${COMMIT_HASH}"
echo "[INFO] Environment   : ${APP_ENV}"
echo "[INFO] Staging Mode  : ${STAGING_MODE}"
echo "[INFO] Dry Run       : ${DRY_RUN}"
echo "[INFO] Local Only    : ${LOCAL_ONLY}"

# Prepare arguments for the builder script
BUILDER_ARGS=()
if [ "$DRY_RUN" = true ]; then
    BUILDER_ARGS+=("--dry-run")
fi
if [ "$LOCAL_ONLY" = true ]; then
    BUILDER_ARGS+=("--local-only")
fi
if [ "$METADATA_ONLY" = true ]; then
    BUILDER_ARGS+=("--metadata-only")
fi

echo ""
echo "=== Step 1: Preflight Verification Probes ==="

# Define a function to execute preflight command and capture output safely
run_preflight() {
    local cmd_name="$1"
    local cmd_str="$2"
    echo -n "Running ${cmd_name}... "
    
    if ! OUTPUT=$(${cmd_str} 2>&1); then
        echo -e "\e[31m[FAILED]\e[0m"
        echo "Command output:"
        echo "$OUTPUT"
        return 1
    else
        echo -e "\e[32m[PASS]\e[0m"
        return 0
    fi
}

PREFLIGHT_FAIL=false

if [ "$METADATA_ONLY" = false ]; then
    # Run active baseline probes
    run_preflight "booking:doctor" "php artisan booking:doctor --json" || PREFLIGHT_FAIL=true
    run_preflight "notifications:outbox-health" "php artisan notifications:outbox-health --json" || PREFLIGHT_FAIL=true
    run_preflight "booking:deploy-check" "php artisan booking:deploy-check --mode=preflight --strict --json" || PREFLIGHT_FAIL=true
fi

run_preflight "booking:release-manifest" "php artisan booking:release-manifest --json" || PREFLIGHT_FAIL=true

if [ "$PREFLIGHT_FAIL" = true ]; then
    echo "[WARNING] One or more preflight verification checks failed."
    echo "Staging rehearsal will continue, but the final launch readiness gate is likely to be BLOCKED."
else
    echo "[INFO] Preflight checks successfully passed."
fi

echo ""
echo "=== Step 2: Compile Manual Evidence Pack ==="
if ! ./scripts/ops/build-staging-evidence-pack.sh "${BUILDER_ARGS[@]}"; then
    echo "[ERROR] Evidence pack compilation failed."
    exit 1
fi

echo ""
echo "=== Step 3: Launch Readiness Gate ==="
EVIDENCE_PACK_PATH="storage/app/booking_release/manual_evidence/manual_evidence.json"

READINESS_ARGS=(
    "--target=staging"
    "--json"
)

if [ -f "$EVIDENCE_PACK_PATH" ] && [ "$DRY_RUN" = false ]; then
    READINESS_ARGS+=("--manual-evidence=${EVIDENCE_PACK_PATH}")
    echo "[INFO] Evaluating launch readiness with manual evidence pack..."
else
    echo "[INFO] Evaluating launch readiness without manual evidence pack..."
fi

# Run launch readiness evaluation
if ! READINESS_OUTPUT=$(php artisan booking:launch-readiness "${READINESS_ARGS[@]}"); then
    echo -e "\e[31m[BLOCKED]\e[0m Launch-readiness gate reported failures!"
    echo "$READINESS_OUTPUT"
    exit 1
fi

echo "=== Staging Rehearsal Report ==="
# Parse readiness decision using inline PHP for robustness
php -r '
$output = json_decode($argv[1], true);
$decision = strtoupper($output["decision"] ?? "UNKNOWN");
$exitCode = (int) ($output["exit_code"] ?? 1);

echo "Rehearsal Decision: ";
if ($exitCode === 0) {
    echo "\e[32mPASS\e[0m\n";
} elseif ($exitCode === 2) {
    echo "\e[33mWARNINGS\e[0m (Requires operator review)\n";
} else {
    echo "\e[31mBLOCKED\e[0m\n";
}

echo "Summary of matrix checks:\n";
foreach ((array)($output["groups"] ?? []) as $group) {
    printf("  - [%-35s]: %s (Failures: %d, Warnings: %d)\n", 
        $group["label"] ?? $group["key"], 
        strtoupper($group["status"] ?? "unknown"),
        $group["blocking_failure_count"] ?? 0,
        $group["major_warning_count"] ?? 0
    );
}

if ($exitCode === 0) {
    echo "\n[INFO] All staging preflights and manual evidence criteria are valid.\n";
} else {
    echo "\n[INFO] Open blockers or manual checklists are still pending before promotion.\n";
}
' -- "$READINESS_OUTPUT"

echo "========================================================="
echo "          Staging Rehearsal Completed Successfully       "
echo "========================================================="
exit 0
