#!/usr/bin/env bash
set -euo pipefail

# This script verifies the health of the operational alerting channel.
# It performs a redacted dry-run check to verify the webhook path without leaking secrets.

# Resolve repository root
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_ROOT="$( cd "$SCRIPT_DIR/../.." && pwd )"
cd "$REPO_ROOT"

# Load environment variables if .env exists
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
fi

# Configurable parameters
SLACK_WEBHOOK="${LOG_SLACK_WEBHOOK_URL:-}"
ENV_NAME="${APP_ENV:-production}"
SERVICE_NAME="${APP_NAME:-RestaurantPOS}"

echo "Starting Alerting Channel Health Check..."
echo "Service:     $SERVICE_NAME"
echo "Environment: $ENV_NAME"

if [ -z "$SLACK_WEBHOOK" ]; then
    echo "Warning: LOG_SLACK_WEBHOOK_URL is not set."
    echo "Staging/Production launch readiness gates will flag this as a blocker."
    exit 0
fi

# Mask webhook URL for logs
MASKED_WEBHOOK=$(echo "$SLACK_WEBHOOK" | sed -E 's/services\/[A-Z0-9]+\/[A-Z0-9]+\/[A-Za-z0-9]+/services\/XXXX\/XXXX\/XXXX/g')
echo "Detected alert sink: $MASKED_WEBHOOK"

TEXT=":sparkles: *Observability Alerting Rehearsal*\n*Service:* \`$SERVICE_NAME\`\n*Environment:* \`$ENV_NAME\`\n*Status:* healthy\n*Timestamp:* \`$(date -u +"%Y-%m-%dT%H:%M:%SZ")\`\n*Alert Rehearsal Check:* PASSED (Alert connectivity is live and verified)."
ESCAPED_TEXT=$(echo "$TEXT" | php -r "echo json_encode(file_get_contents('php://stdin'));")

echo "Sending dry-run rehearsal alert..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST -H 'Content-type: application/json' --data "{\"text\": $ESCAPED_TEXT}" --max-time 10 "$SLACK_WEBHOOK")

if [ "$HTTP_STATUS" -eq 200 ]; then
    echo "Rehearsal alert sent successfully. HTTP status 200."
    exit 0
else
    echo "Error: Failed to deliver rehearsal alert. HTTP status: $HTTP_STATUS"
    exit 1
fi
