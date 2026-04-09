#!/usr/bin/env bash
set -euo pipefail

php artisan booking:doctor --strict
php artisan booking:deploy-check --mode=preflight --strict
