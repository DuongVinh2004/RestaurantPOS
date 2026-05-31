#!/usr/bin/env bash

# start-scheduler-heartbeat.sh
# Staging/Local Scheduler Heartbeat Operator Lane

set -euo pipefail

# Locate repo root
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$DIR/../.." && pwd)"
cd "$REPO_ROOT"

# Resolve PHP binary
PHP_BIN="${PHP_BIN:-php}"

check_php() {
  if ! command -v "$PHP_BIN" &> /dev/null; then
    echo "Error: PHP executable '$PHP_BIN' is not found in PATH."
    echo "Please configure PHP_BIN environment variable or add PHP to your PATH."
    exit 1
  fi

  if [ ! -f "artisan" ]; then
    echo "Error: 'artisan' not found in repo root '$REPO_ROOT'."
    exit 1
  fi
}

show_help() {
  echo "Staging/Local Scheduler Heartbeat Operator Lane"
  echo "Usage: $0 [option]"
  echo ""
  echo "Options:"
  echo "  --once               Touch the scheduler heartbeat once and exit (for validation)."
  echo "  --watch              Run a foreground loop to keep touching the heartbeat every 60 seconds."
  echo "  --supervisor-sample  Print the sample Supervisor configuration for staging and exit."
  echo "  --help, -h           Show this help message."
}

show_supervisor_sample() {
  echo "================================================================================"
  echo "Sample Supervisor Configuration for Staging Scheduler / Queue Workers"
  echo "================================================================================"
  echo "Place this configuration in /etc/supervisor/conf.d/restaurantpos-scheduler.conf:"
  echo ""
  echo "[program:restaurantpos-scheduler]"
  echo "process_name=%(program_name)s"
  echo "command=php /var/www/restaurantpos/artisan schedule:work"
  echo "user=www-data"
  echo "autostart=true"
  echo "autorestart=true"
  echo "stopasgroup=true"
  echo "killasgroup=true"
  echo "redirect_stderr=true"
  echo "stdout_logfile=/var/www/restaurantpos/storage/logs/scheduler-daemon.log"
  echo ""
  echo "To apply:"
  echo "  sudo supervisorctl reread"
  echo "  sudo supervisorctl update"
  echo "  sudo supervisorctl start restaurantpos-scheduler"
  echo "================================================================================"
}

run_once() {
  check_php
  echo "Touching scheduler heartbeat once..."
  "$PHP_BIN" artisan booking:ops-heartbeat:touch scheduler --json
  echo "Heartbeat touched successfully."
}

run_watch() {
  check_php
  echo "Starting scheduler heartbeat watch loop (every 60s)... Press Ctrl+C to stop."
  trap "echo -e '\nWatch loop stopped cleanly.'; exit 0" SIGINT SIGTERM
  
  while true; do
    "$PHP_BIN" artisan booking:ops-heartbeat:touch scheduler --json
    sleep 60
  done
}

# Parse options
OPTION="${1:-}"

case "$OPTION" in
  --once)
    run_once
    ;;
  --watch)
    run_watch
    ;;
  --supervisor-sample)
    show_supervisor_sample
    ;;
  --help|-h|"")
    if [ -z "$OPTION" ]; then
      show_help
      exit 1
    else
      show_help
    fi
    ;;
  *)
    echo "Unknown option: $OPTION"
    show_help
    exit 1
    ;;
esac
