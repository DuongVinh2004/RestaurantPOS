#!/usr/bin/env bash

# This script checks the outbox health and sends a Slack alert if it fails.
# Setup this in crontab: * * * * * /path/to/check-outbox-health.sh

cd /var/www/html

OUTPUT=$(php artisan notifications:outbox-health 2>&1)
EXIT_CODE=$?

if [ $EXIT_CODE -ne 0 ]; then
    if [ -n "$LOG_SLACK_WEBHOOK_URL" ]; then
        curl -X POST -H 'Content-type: application/json' --data "{\"text\":\":rotating_light: *Outbox Health Check Failed*\n\`\`\`$OUTPUT\`\`\`\"}" $LOG_SLACK_WEBHOOK_URL
    else
        echo "LOG_SLACK_WEBHOOK_URL is not set."
    fi
fi
