#!/usr/bin/env bash
# check-staging-credentials.sh
# Sanity check staging environment variables and credentials without exposing secret values.

set -euo pipefail

# Print help description
usage() {
    echo "Usage: $0 [options]"
    echo "Options:"
    echo "  --json        Output result as machine-readable JSON"
    echo "  --strict      Exit non-zero (code 2) if any optional external SaaS credentials are missing"
    echo "  --local-only  Do not fail on optional external SaaS credentials even in --strict mode"
    echo "  -h, --help    Show this help message"
    exit 0
}

JSON_OUTPUT=false
STRICT_MODE=false
LOCAL_ONLY=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --json)
            JSON_OUTPUT=true
            shift
            ;;
        --strict)
            STRICT_MODE=true
            shift
            ;;
        --local-only)
            LOCAL_ONLY=true
            shift
            ;;
        -h|--help)
            usage
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

# Scrape .env if available, without overwriting system env vars
declare -A ENV_VARS
if [ -f .env ]; then
    while IFS= read -r line || [ -n "$line" ]; do
        # Ignore comments and empty lines
        if [[ "$line" =~ ^[[:space:]]*# ]] || [[ -z "${line// }" ]]; then
            continue
        fi
        # Split key and value
        if [[ "$line" =~ ^([^=]+)=(.*)$ ]]; then
            KEY="${BASH_REMATCH[1]}"
            VAL="${BASH_REMATCH[2]}"
            # Strip outer quotes if any
            VAL="${VAL%\"}"
            VAL="${VAL#\"}"
            VAL="${VAL%\'}"
            VAL="${VAL#\'}"
            ENV_VARS["$KEY"]="$VAL"
        fi
    done < .env
fi

# Helper to read environment variables prioritising system variables over .env
get_val() {
    local var_name="$1"
    if env | grep -q "^${var_name}="; then
        printenv "$var_name"
    elif [[ -n "${ENV_VARS[$var_name]+exists}" ]]; then
        echo "${ENV_VARS[$var_name]}"
    else
        echo ""
    fi
}

# Define checked variables
CORE_VARS=(
    "APP_ENV"
    "APP_URL"
    "DB_HOST"
    "DB_DATABASE"
    "DB_USERNAME"
    "DB_PASSWORD"
    "REDIS_HOST"
)

EXTERNAL_VARS=(
    "BACKUP_S3_BUCKET"
    "BACKUP_S3_PREFIX"
    "AWS_REGION"
    "AWS_ACCESS_KEY_ID"
    "MAIL_MAILER"
    "MAIL_HOST"
    "MAIL_USERNAME"
    "MAIL_PASSWORD"
    "MAIL_FROM_ADDRESS"
    "NOTIFICATION_SMOKE_EMAIL"
    "SENTRY_LARAVEL_DSN"
    "SENTRY_ENVIRONMENT"
    "OPS_ALERTS_WEBHOOK_URL"
    "VNPAY_TMN_CODE"
    "VNPAY_HASH_SECRET"
    "VNPAY_IPN_URL"
    "MOMO_PARTNER_CODE"
    "MOMO_ACCESS_KEY"
    "MOMO_SECRET_KEY"
    "MOMO_IPN_URL"
    "STAGING_BASE_URL"
)

# Placeholders to check
PLACEHOLDER_REGEX="^(changeme|example|placeholder|your-.*|null|local-simulated-.*|sandbox-.*-secret.*)$"

check_status() {
    local val="$1"
    if [ -z "$val" ]; then
        echo "MISSING"
    elif [[ "$val" =~ $PLACEHOLDER_REGEX ]]; then
        echo "PLACEHOLDER"
    else
        echo "SET"
    fi
}

CORE_MISSING=false
EXTERNAL_MISSING=false

# Pre-evaluating
declare -A VAR_STATUS
for var in "${CORE_VARS[@]}" "${EXTERNAL_VARS[@]}"; do
    VAL=$(get_val "$var")
    VAR_STATUS["$var"]=$(check_status "$VAL")
    if [[ "${VAR_STATUS[$var]}" != "SET" ]]; then
        # Check if it's core
        for core in "${CORE_VARS[@]}"; do
            if [[ "$core" == "$var" ]]; then
                CORE_MISSING=true
            fi
        done
        # Check if it's external
        for ext in "${EXTERNAL_VARS[@]}"; do
            if [[ "$ext" == "$var" ]]; then
                EXTERNAL_MISSING=true
            fi
        done
    fi
done

# Output Logic
if [ "$JSON_OUTPUT" = true ]; then
    # Custom JSON formatting without jq
    echo "{"
    echo "  \"ok\": $([ "$CORE_MISSING" = false ] && ([ "$EXTERNAL_MISSING" = false ] || [ "$STRICT_MODE" = false ] || [ "$LOCAL_ONLY" = true ]) && echo "true" || echo "false"),"
    echo "  \"strict\": $STRICT_MODE,"
    echo "  \"local_only\": $LOCAL_ONLY,"
    echo "  \"summary\": {"
    echo "    \"core_runtime_ok\": $([ "$CORE_MISSING" = false ] && echo "true" || echo "false"),"
    echo "    \"external_saas_ok\": $([ "$EXTERNAL_MISSING" = false ] && echo "true" || echo "false")"
    echo "  },"
    echo "  \"variables\": {"
    
    ALL_VARS=("${CORE_VARS[@]}" "${EXTERNAL_VARS[@]}")
    LENGTH=${#ALL_VARS[@]}
    for ((i=0; i<LENGTH; i++)); do
        var="${ALL_VARS[i]}"
        IS_CORE=false
        for core in "${CORE_VARS[@]}"; do
            if [[ "$core" == "$var" ]]; then
                IS_CORE=true
            fi
        done
        
        COMMA=","
        if [ $i -eq $((LENGTH-1)) ]; then
            COMMA=""
        fi
        
        echo "    \"$var\": {"
        echo "      \"status\": \"${VAR_STATUS[$var]}\","
        echo "      \"classification\": \"$([ "$IS_CORE" = true ] && echo "core" || echo "external")\""
        echo "    }$COMMA"
    done
    echo "  }"
    echo "}"
else
    # Text output
    echo "Staging Credentials Sanity Check:"
    echo "----------------------------------------"
    echo "[CORE RUNTIME VARIABLES]"
    for var in "${CORE_VARS[@]}"; do
        printf "  %-30s: %s\n" "$var" "${VAR_STATUS[$var]}"
    done
    echo ""
    echo "[EXTERNAL SAAS VARIABLES]"
    for var in "${EXTERNAL_VARS[@]}"; do
        printf "  %-30s: %s\n" "$var" "${VAR_STATUS[$var]}"
    done
    echo "----------------------------------------"
    
    if [ "$CORE_MISSING" = true ]; then
        echo "[ERROR] Scoped Core Runtime credentials are MISSING or PLACEHOLDER."
        echo "Cannot start basic runtime. Resolve DB/Redis configuration."
    elif [ "$EXTERNAL_MISSING" = true ]; then
        if [ "$STRICT_MODE" = true ] && [ "$LOCAL_ONLY" = false ]; then
            echo "[ERROR] STRICT MODE: Staging SaaS credentials are MISSING/PLACEHOLDER."
        else
            echo "[WARNING] Optional staging SaaS credentials are MISSING/PLACEHOLDER."
            echo "Staging environment will run in local-rehearsal/simulated mode."
        fi
    else
        echo "[SUCCESS] Staging credentials are fully populated!"
    fi
fi

# Exit code logic
if [ "$CORE_MISSING" = true ]; then
    exit 1
fi

if [ "$EXTERNAL_MISSING" = true ] && [ "$STRICT_MODE" = true ] && [ "$LOCAL_ONLY" = false ]; then
    exit 2
fi

exit 0
