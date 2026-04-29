#!/usr/bin/env bash

booking_ci_escape_github_annotation() {
  local data="$1"
  data="${data//'%'/'%25'}"
  data="${data//$'\r'/'%0D'}"
  data="${data//$'\n'/'%0A'}"
  printf '%s' "$data"
}

booking_ci_run_step() {
  local step_id="$1"
  local step_name="$2"
  local timeout_seconds="$3"
  local log_path="$4"
  local command_string="$5"

  mkdir -p "$(dirname "$log_path")" build/booking-ci storage/logs

  {
    printf '[booking-ci] step: %s\n' "$step_name"
    printf '[booking-ci] command: %s\n' "$command_string"
    printf '[booking-ci] timeout_seconds: %s\n' "$timeout_seconds"
    printf '[booking-ci] started_at_utc: %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
  } | tee "$log_path"

  printf 'step_id=%s\nstep_name=%s\ncommand=%s\ntimeout_seconds=%s\n' \
    "$step_id" "$step_name" "$command_string" "$timeout_seconds" > "${log_path}.meta"

  local status
  local restore_errexit=0
  case "$-" in
    *e*)
      restore_errexit=1
      set +e
      ;;
  esac

  if command -v timeout >/dev/null 2>&1 && [[ "$timeout_seconds" =~ ^[0-9]+$ ]] && [[ "$timeout_seconds" -gt 0 ]]; then
    timeout "${timeout_seconds}s" bash -o pipefail -c "$command_string" 2>&1 | tee -a "$log_path"
    status="${PIPESTATUS[0]}"
  else
    printf '[booking-ci] timeout command unavailable; running without shell-level timeout.\n' | tee -a "$log_path"
    bash -o pipefail -c "$command_string" 2>&1 | tee -a "$log_path"
    status="${PIPESTATUS[0]}"
  fi

  if [[ "$restore_errexit" -eq 1 ]]; then
    set -e
  fi

  printf '[booking-ci] finished_at_utc: %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" | tee -a "$log_path"
  printf '[booking-ci] exit_code: %s\n' "$status" | tee -a "$log_path"
  printf 'exit_code=%s\n' "$status" >> "${log_path}.meta"

  if [[ "$status" -ne 0 ]]; then
    local failure_summary
    if [[ "$status" -eq 124 ]]; then
      failure_summary="$(printf 'Command timed out after %s seconds: %s' "$timeout_seconds" "$command_string")"
    else
      failure_summary="$(printf 'Command failed with exit code %s: %s' "$status" "$command_string")"
    fi
    printf '[booking-ci] %s\n' "$failure_summary" | tee -a "$log_path"

    local log_tail
    log_tail="$(tail -n 120 "$log_path" 2>/dev/null || true)"
    if [[ -n "${GITHUB_ACTIONS:-}" ]]; then
      printf '::error title=%s failed::%s\n' "$step_name" "$(booking_ci_escape_github_annotation "${failure_summary}"$'\n'"${log_tail}")"
    fi

    return "$status"
  fi

  return 0
}
