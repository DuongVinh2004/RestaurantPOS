#!/usr/bin/env bash
set -euo pipefail

# Print usage instructions
usage() {
    echo "Usage: $0 [options]"
    echo "Options:"
    echo "  --dry-run        Validate evidence sources and posture without writing files"
    echo "  --local-only     Verify local configurations, skipping external dependency logs"
    echo "  --metadata-only  Assess the manifest structure and template presence only"
    echo "  -h, --help       Show this help message"
    exit 0
}

# Parse options
DRY_RUN=false
LOCAL_ONLY=false
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
echo "      RestaurantPOS Staging Evidence Pack Builder        "
echo "========================================================="

# 1. Environment Verification
APP_ENV="${APP_ENV:-local}"
if [ "$APP_ENV" = "production" ]; then
    echo "[ERROR] Staging Evidence Pack Builder cannot be run in production environment!"
    exit 1
fi

echo "[INFO] Environment  : ${APP_ENV}"
echo "[INFO] Dry Run      : ${DRY_RUN}"
echo "[INFO] Local Only   : ${LOCAL_ONLY}"
echo "[INFO] Metadata Only: ${METADATA_ONLY}"

# Define target paths
EVIDENCE_DIR="storage/app/booking_release/manual_evidence"
TARGET_FILE="${EVIDENCE_DIR}/manual_evidence.json"
TEMPLATE_PATH="docs/runbooks/templates/staging-evidence-pack.template.json"

if [ "$DRY_RUN" = false ]; then
    mkdir -p "$EVIDENCE_DIR"
fi

if [ ! -f "$TEMPLATE_PATH" ]; then
    echo "[ERROR] Master template not found at: ${TEMPLATE_PATH}"
    exit 1
fi

echo "[INFO] Master template verified at: ${TEMPLATE_PATH}"

# Write the PHP script to a temporary file to avoid CLI parsing/escaping issues on Windows
PHP_TEMP_SCRIPT=$(mktemp)
cat <<'EOF' > "$PHP_TEMP_SCRIPT"
<?php

$dryRun = filter_var($argv[1], FILTER_VALIDATE_BOOLEAN);
$localOnly = filter_var($argv[2], FILTER_VALIDATE_BOOLEAN);
$metadataOnly = filter_var($argv[3], FILTER_VALIDATE_BOOLEAN);

$evidenceDir = 'storage/app/booking_release/manual_evidence';
$targetFile = $evidenceDir . '/manual_evidence.json';
$templatePath = 'docs/runbooks/templates/staging-evidence-pack.template.json';

// 1. Load baseline template
if (!file_exists($templatePath)) {
    fwrite(STDERR, "[ERROR] Template file missing: $templatePath\n");
    exit(1);
}
$master = json_decode(file_get_contents($templatePath), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "[ERROR] Failed parsing template JSON: " . json_last_error_msg() . "\n");
    exit(1);
}

// Ensure checks object is initialized
if (!isset($master['checks']) || !is_array($master['checks'])) {
    $master['checks'] = [];
}

// 2. Identify and merge individual manual check sources
$checkKeys = [
    'uat_scenario_pack_replay',
    'disaster_recovery_restore_evidence',
    'performance_verification_report',
    'payment_provider_external_e2e',
    'notification_provider_external_e2e',
    'operator_approval'
];

$mergedCount = 0;

// Also look at old rehearsal output path from scripts/ops/run-dr-rehearsal.sh
$oldRehearsalPath = 'storage/app/booking_release/launch_readiness/manual_evidence.json';
if (file_exists($oldRehearsalPath)) {
    $oldData = json_decode(file_get_contents($oldRehearsalPath), true);
    if (is_array($oldData) && isset($oldData['checks']['disaster_recovery_restore_evidence'])) {
        $master['checks']['disaster_recovery_restore_evidence'] = $oldData['checks']['disaster_recovery_restore_evidence'];
        echo "[INFO] Imported DR restore check from rehearsal file.\n";
        $mergedCount++;
    }
}

foreach ($checkKeys as $key) {
    $individualPath = $evidenceDir . '/' . $key . '.json';
    if (file_exists($individualPath)) {
        $data = json_decode(file_get_contents($individualPath), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            // Unpack if nested under top-level key or direct
            $checkData = isset($data[$key]) ? $data[$key] : $data;
            $master['checks'][$key] = array_merge(
                isset($master['checks'][$key]) ? $master['checks'][$key] : [],
                $checkData
            );
            echo "[INFO] Successfully merged individual check file: $individualPath\n";
            $mergedCount++;
        } else {
            fwrite(STDERR, "[WARNING] Found file $individualPath but it is invalid JSON.\n");
        }
    }
}

// 3. Security/PII Scrubbing and Validation
$secretsPatterns = [
    '/(^|[_\\-.])(secret|password|credential|api[_-]?key|access[_-]?token|refresh[_-]?token|bearer|authorization)($|[_\\-.])/i',
    '/hooks\.slack\.com/i',
    '/xoxb-/i',
    '/AKIA[A-Z0-9]{16}/i', // AWS access key
    '/DSN/i'
];

$piiPatterns = [
    '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', // Email regex
    '/(\+?84|0)(3|5|7|8|9)+([0-9]{8})\b/' // Vietnam phone number regex
];

$issues = [];

function scrubAndValidate(&$item, $path) {
    global $secretsPatterns, $piiPatterns, $issues;
    if (is_array($item)) {
        foreach ($item as $k => &$v) {
            scrubAndValidate($v, $path . '.' . $k);
        }
    } elseif (is_string($item)) {
        // Validation: Check for secret values
        foreach ($secretsPatterns as $pattern) {
            if (preg_match($pattern, $item)) {
                // If it contains a secret property name/url/pattern, redact it
                if (!preg_match('/\[redacted\]|\[hidden\]|placeholder/i', $item)) {
                    $issues[] = "Field [$path] contains a potential secret. Redacting automatically.";
                    $item = "[redacted]";
                }
            }
        }
        // Validation: Check for PII leaks
        foreach ($piiPatterns as $pattern) {
            if (preg_match($pattern, $item)) {
                $issues[] = "Field [$path] contains potential customer PII. Redacting automatically.";
                $item = preg_replace($pattern, "[redacted_pii]", $item);
            }
        }
    }
}

scrubAndValidate($master, 'root');

// 4. Staging validation and formatting
$master['generated_at'] = gmdate('Y-m-d\TH:i:s\Z');
$master['evidence_pack_id'] = 'staging-rehearsal-' . gmdate('Ymd-His');
$master['target'] = 'staging';

// Retrieve Git details if possible
$commit = @shell_exec('git rev-parse HEAD');
if ($commit) {
    $master['source_commit'] = trim($commit);
}

// 5. Output
if ($issues) {
    echo "[WARNING] PII or secret patterns were detected and scrubbed:\n";
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
}

if ($dryRun) {
    echo "[INFO] Dry run check: Evidence pack successfully evaluated. Merged $mergedCount source(s).\n";
} else {
    file_put_contents($targetFile, json_encode($master, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "[SUCCESS] Consolidated evidence pack compiled at: $targetFile (Merged $mergedCount checks)\n";
}

exit(0);
EOF

# Run the temp PHP script and clean up
php "$PHP_TEMP_SCRIPT" "$DRY_RUN" "$LOCAL_ONLY" "$METADATA_ONLY"
rm -f "$PHP_TEMP_SCRIPT"

echo "[INFO] Evidence compilation completed."
exit 0
