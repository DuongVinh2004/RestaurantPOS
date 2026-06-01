#!/usr/bin/env bash
set -euo pipefail

# This script checks the outbox health and sends a Slack alert if it is unhealthy.
# It resolves the base directory dynamically to support staging, local, and production environments.

# Resolve repository root
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_ROOT="$( cd "$SCRIPT_DIR/../.." && pwd )"
cd "$REPO_ROOT"

# Load environment variables safely if .env exists
if [ -f .env ]; then
    set -a
    source .env
    set +a
fi

# Configurable parameters
SLACK_WEBHOOK="${LOG_SLACK_WEBHOOK_URL:-}"
ENV_NAME="${APP_ENV:-production}"
SERVICE_NAME="${APP_NAME:-RestaurantPOS}"

# Run notifications outbox health check (timeout 15s to prevent hanging)
JSON_OUTPUT=""
EXIT_CODE=0

if ! JSON_OUTPUT=$(timeout 15s php artisan notifications:outbox-health --json 2>&1); then
    EXIT_CODE=$?
fi

# If the command failed or timed out, trigger a critical alert
if [ $EXIT_CODE -ne 0 ]; then
    echo "Outbox health check failed or timed out with exit code $EXIT_CODE."
    
    # Redact sensitive trace info/credentials from the raw output
    REDACTED_OUTPUT=$(echo "$JSON_OUTPUT" | sed -E 's/password=[^& ]+/password=REDACTED/g' | sed -E 's/key=[^& ]+/key=REDACTED/g')

    if [ -n "$SLACK_WEBHOOK" ]; then
        TEXT=":rotating_light: *Outbox Health Probe Failure*\n*Service:* \`$SERVICE_NAME\`\n*Environment:* \`$ENV_NAME\`\n*Timestamp:* \`$(date -u +"%Y-%m-%dT%H:%M:%SZ")\`\n*Exit Code:* \`$EXIT_CODE\`\n*Probe Logs:*\n\`\`\`\n$REDACTED_OUTPUT\n\`\`\`"
        # Safely escape text for JSON
        ESCAPED_TEXT=$(echo "$TEXT" | php -r "echo json_encode(file_get_contents('php://stdin'));")
        
        curl -s -S -X POST -H 'Content-type: application/json' --data "{\"text\": $ESCAPED_TEXT}" --max-time 10 "$SLACK_WEBHOOK" > /dev/null || true
    else
        echo "LOG_SLACK_WEBHOOK_URL is not set. Redacted probe log:"
        echo "$REDACTED_OUTPUT"
    fi
    exit $EXIT_CODE
fi

# Parse JSON ok flag using a robust stdin PHP-json parser (independent of jq)
IS_OK=$(echo "$JSON_OUTPUT" | php -r "
    \$json = json_decode(file_get_contents('php://stdin'), true);
    echo (is_array(\$json) && (\$json['ok'] ?? false)) ? 'true' : 'false';
")

if [ "$IS_OK" != "true" ]; then
    echo "Outbox health is degraded."
    
    # Parse metrics safely
    PENDING_COUNT=$(echo "$JSON_OUTPUT" | php -r "\$j = json_decode(file_get_contents('php://stdin'), true); echo \$j['pending_count'] ?? 0;")
    FAILED_COUNT=$(echo "$JSON_OUTPUT" | php -r "\$j = json_decode(file_get_contents('php://stdin'), true); echo \$j['failed_count'] ?? 0;")
    STALE_COUNT=$(echo "$JSON_OUTPUT" | php -r "\$j = json_decode(file_get_contents('php://stdin'), true); echo \$j['stale_processing_count'] ?? 0;")
    DEAD_LETTER_COUNT=$(echo "$JSON_OUTPUT" | php -r "\$j = json_decode(file_get_contents('php://stdin'), true); echo \$j['dead_letter_count'] ?? 0;")
    ERROR_MSG=$(echo "$JSON_OUTPUT" | php -r "\$j = json_decode(file_get_contents('php://stdin'), true); echo \$j['error'] ?? 'Unspecified outbox degradation.';")

    if [ -n "$SLACK_WEBHOOK" ]; then
        TEXT=":warning: *Outbox Health Warning*\n*Service:* \`$SERVICE_NAME\`\n*Environment:* \`$ENV_NAME\`\n*Status:* degraded\n*Timestamp:* \`$(date -u +"%Y-%m-%dT%H:%M:%SZ")\`\n*Pending:* \`$PENDING_COUNT\` | *Failed:* \`$FAILED_COUNT\` | *Stale:* \`$STALE_COUNT\` | *Dead Letter:* \`$DEAD_LETTER_COUNT\`\n*Error Detail:* \`$ERROR_MSG\`"
        ESCAPED_TEXT=$(echo "$TEXT" | php -r "echo json_encode(file_get_contents('php://stdin'));")
        
        curl -s -S -X POST -H 'Content-type: application/json' --data "{\"text\": $ESCAPED_TEXT}" --max-time 10 "$SLACK_WEBHOOK" > /dev/null || true
    else
        echo "LOG_SLACK_WEBHOOK_URL is not set. Outbox degraded: $ERROR_MSG"
    fi
    exit 1
fi

echo "Outbox is healthy."
exit 0
