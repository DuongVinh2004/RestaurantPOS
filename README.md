# RestaurantPOS

[![Backend CI](https://github.com/DuongVinh2004/RestaurantPOS/actions/workflows/booking-ci.yml/badge.svg?branch=main)](https://github.com/DuongVinh2004/RestaurantPOS/actions/workflows/booking-ci.yml)
[![Release Gate](https://github.com/DuongVinh2004/RestaurantPOS/actions/workflows/booking-release-gate.yml/badge.svg?branch=main)](https://github.com/DuongVinh2004/RestaurantPOS/actions/workflows/booking-release-gate.yml)

> [!NOTE]
> **New to the project?** Please read the **[Project Handoff Overview](./docs/PROJECT_HANDOFF.md)** and our **[Known Limitations](./docs/product/known-limitations.md)** before diving in.

RestaurantPOS is a SQL-first Laravel 12 backend with staff and customer web clients, API consumer artifacts, and release/runtime gates for production-style restaurant operations.

The repository is intentionally not migration-first. A fresh machine must be provisioned from the checked-in SQL release contract:

1. `database/schema/mysql-schema.sql`
2. every `database/patches/*.sql` file in lexical order
3. `tools/mysql/verify_release_contract.sql`
4. `composer bootstrap:booking` for database bootstrap, reference data, site bootstrap, reporting snapshots, release artifacts, and scheduler heartbeat priming

Do not use `php artisan migrate` as the default local, staging, or release bootstrap path.

## What This Repo Runs

- Laravel backend API for reservations, table holds, waiting list, dine-in POS, checkout, refunds, cashier shifts, inventory, reporting, notifications, and operational gates
- `staff-web/`: staff-facing React + Vite client
- `customer-web/`: customer-facing Next.js client
- `build/api-consumer/`: generated SDK, Postman collection, enum/state map, and mutation contract artifacts
- `storage/app/booking_release/`: frozen release evidence and manifest snapshots used by release gates

## Prerequisites

Install these before cloning or bootstrapping:

- Git
- PHP 8.2 or newer, with Composer resolving against the repo's PHP 8.2 platform
- Composer 2
- Node.js 20 LTS or newer, plus npm
- MySQL 8 compatible server and the `mysql` CLI
- Redis 7 compatible server
- Docker Desktop or Docker Engine, optional but useful for local MySQL/Redis fallback

Recommended PHP extensions:

- `ctype`
- `curl`
- `fileinfo`
- `mbstring`
- `openssl`
- `pdo_mysql`
- `pdo_sqlite`
- `redis` or `phpredis` when Redis-backed runtime gates are enabled
- `tokenizer`
- `xml`

Check the tools:

```bash
php -v
composer -V
node -v
npm -v
mysql --version
redis-cli --version
```

On Windows, use PowerShell for repo helper scripts. The Windows local MySQL helper requires MySQL Server 8 compatible `mysqld.exe`; older XAMPP/MariaDB runtimes are not a reliable substitute for the SQL-first release contract.

## Fresh Clone Setup

These steps are the canonical path for a new machine.

### 1. Clone and enter the repo

```bash
git clone https://github.com/DuongVinh2004/RestaurantPOS.git
cd RestaurantPOS
```

### 2. Install backend dependencies

```bash
composer install
```

### 3. Create `.env`

Windows:

```powershell
copy .env.example .env
```

macOS / Linux:

```bash
cp .env.example .env
```

Edit `.env` before bootstrapping. For a local Docker-backed setup, use:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=restaurantdb
DB_USERNAME=root
DB_PASSWORD=123456

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REQUIRE_REDIS_FOR_BOOKING_API=false
```

If you use an existing MySQL service, set `DB_USERNAME` and `DB_PASSWORD` to the real local credentials. If `mysql` is not on `PATH`, set:

```env
MYSQL_BIN=/absolute/path/to/mysql
```

On Windows, use a Windows path:

```env
MYSQL_BIN=C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe
MYSQLD_BIN=C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe
```

### 4. Generate the Laravel app key

```bash
php artisan key:generate --ansi --force
```

### 5. Start MySQL and Redis

Use one of these options.

Docker local fallback:

```bash
docker compose -f docker-compose.testing.yml up -d mysql redis
```

Windows repo-local helpers:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\ops\start-local-mysql.ps1 -Restart
powershell -ExecutionPolicy Bypass -File scripts\ops\start-local-redis.ps1 -Restart
```

Existing services:

- Start your own MySQL 8 compatible service on `DB_HOST:DB_PORT`.
- Start your own Redis service on `REDIS_HOST:REDIS_PORT`.
- Confirm `.env` matches those services.

Confirm connectivity:

```bash
mysql --protocol=tcp -h 127.0.0.1 -P 3306 -u root -p -e "SELECT 1"
redis-cli -h 127.0.0.1 -p 6379 ping
```

The Redis command should return `PONG`.

### 6. Bootstrap the SQL-first backend

Run the canonical bootstrap:

```bash
composer bootstrap:booking
```

This command:

- creates the configured database when allowed
- imports `database/schema/mysql-schema.sql`
- applies every SQL patch in `database/patches/`
- runs `tools/mysql/verify_release_contract.sql`
- seeds reference data
- clears Laravel caches
- runs `booking:bootstrap-site`
- rebuilds recent reporting snapshots
- normalizes release artifacts
- refreshes the release manifest report
- primes the scheduler heartbeat once

Use a disposable local database. The bootstrap imports the canonical schema and patches, so it is not a safe command to run casually against a hand-maintained production database.

For CI or platforms that pre-create the database:

```bash
composer bootstrap:booking -- --skip-create-db
```

Database-only bootstrap:

```bash
composer bootstrap:release-db
```

### 7. Start the backend runtime

The managed local runtime starts Laravel HTTP and `schedule:work`, and can reuse or start local MySQL/Redis:

```bash
npm run runtime:up
```

Default backend URL:

```text
http://127.0.0.1:8000
```

Health endpoint:

```text
http://127.0.0.1:8000/api/v1/health
```

Stop the managed runtime:

```bash
npm run runtime:down
```

Manual equivalent, useful when debugging:

```bash
php artisan serve --host=127.0.0.1 --port=8000
php artisan schedule:work
```

Keep `schedule:work` running when you need scheduler heartbeat, reservation expiry, waiting-list expiry, reminders, outbox processing, or reporting freshness.

### 8. Prove the backend is usable

Run:

```bash
npm run runtime:preflight
php artisan booking:doctor --json
php artisan notifications:outbox-health --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:release-manifest --json
```

Expected local state:

- database probe passes
- Redis probe passes
- scheduler heartbeat is fresh
- notification outbox health is inspectable
- deploy preflight has no blocking errors
- release manifest returns `ok=true`

If any of these fail, fix runtime health before debugging feature flows.

## Frontend Setup

The backend can run without the web clients. Install and build only the clients you need.

### Root Vite assets

```bash
npm ci
npm run build
```

### Staff web

Windows:

```powershell
copy staff-web\.env.example staff-web\.env.local
```

macOS / Linux:

```bash
cp staff-web/.env.example staff-web/.env.local
```

Ensure:

```env
VITE_API_URL=http://127.0.0.1:8000/api/v1
```

Install and run:

```bash
cd staff-web
npm ci
npm run dev -- --host 127.0.0.1 --port 5173
```

Build and test:

```bash
npm run test
npm run build
```

### Customer web

Windows:

```powershell
copy customer-web\.env.example customer-web\.env.local
```

macOS / Linux:

```bash
cp customer-web/.env.example customer-web/.env.local
```

Ensure:

```env
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000
NEXT_PUBLIC_ENABLE_DEV_MOCKS=false
```

Install and run:

```bash
cd customer-web
npm ci
npm run dev -- --hostname 127.0.0.1 --port 3000
```

Build and test:

```bash
npm run lint
npm run typecheck
npm run test
npm run build
```

### Backend CORS for local web clients

If the browser clients call the backend directly, set exact local origins in backend `.env`:

```env
CORS_ALLOWED_ORIGINS=http://127.0.0.1:3000,http://localhost:3000,http://127.0.0.1:5173,http://localhost:5173
CORS_SUPPORTS_CREDENTIALS=false
```

Origins must be exact `scheme://host:port` values. Do not include paths or trailing slashes.

### Windows one-command UI lane

After root, staff, and customer npm dependencies are installed:

```bash
npm run dev:all
```

Defaults:

- backend: `http://127.0.0.1:8000`
- customer-web: `http://127.0.0.1:3000`
- staff-web: `http://127.0.0.1:5173`

Then smoke the local UI lane:

```bash
npm run dev:smoke
```

`npm run dev:all` is a Windows PowerShell wrapper. On macOS/Linux, run the backend, scheduler, `customer-web`, and `staff-web` commands in separate terminals.

Daily `dev:all` and `dev:be` preserve existing local database rows once the booking schema exists. To intentionally rebuild the SQL-first database from the release schema and seed/demo data, run:

```bash
npm run dev:all:reset
# or backend only
npm run dev:be:reset
```

## Day-To-Day Workflow

After pulling new code:

```bash
composer install
npm ci
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

Run `composer bootstrap:booking` again when:

- this is a new machine
- the local database was deleted
- `database/schema/mysql-schema.sql` changed
- `database/patches/` changed
- `db_all.sql` changed
- local data is inconsistent and you want a clean reset

Start daily backend runtime:

```bash
npm run runtime:up
npm run runtime:preflight
```

Stop it:

```bash
npm run runtime:down
```

## Verification

Automated PHPUnit tests use SQLite in memory by default through `phpunit.xml`. Passing PHPUnit is useful, but it is not proof that MySQL, Redis, scheduler, outbox, or release bootstrap are healthy.

Use the verification selector first:

```bash
composer verify:select -- --path=app/Services/Staff/StaffCheckoutService.php
php artisan booking:verify-select --base=origin/main --json
```

Common backend checks:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php artisan test
composer test:release-contract
vendor/bin/phpunit --group booking-smoke --testdox
```

Runtime and release checks:

```bash
php artisan booking:doctor --json
php artisan notifications:outbox-health --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:release-manifest --verify-frozen --json
node scripts/release/check-package-integrity.mjs --json
```

CI-like gate scripts are under `scripts/ci/`. They are Bash scripts, so on Windows use Git Bash or a compatible shell:

```bash
bash scripts/ci/booking-smoke-gate.sh
bash scripts/ci/booking-full-gate.sh
```

## API Contracts And Generated Artifacts

Do not hand-maintain frontend API shapes from route files. Use the generated artifacts:

- `storage/app/booking_release/openapi-v1.json`
- `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`
- `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`
- `build/api-consumer/postman/`
- `build/api-consumer/enum-state-map.json`
- `build/api-consumer/mutation-contracts.md`
- `storage/app/booking_release/release_manifest_snapshot.json`

Regenerate the API consumer and release manifest artifacts:

```bash
composer api:artifacts
php artisan booking:release-manifest --verify-frozen --json
```

Read [docs/runbooks/api-consumer-artifacts.md](./docs/runbooks/api-consumer-artifacts.md) before changing generated artifact behavior.

## Local UAT Pack

For local demo and live browser proof, generate the UAT scenario pack after the backend is bootstrapped:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\uat\Bootstrap-UatPack.ps1 -BaseUrl http://127.0.0.1:8000
```

The generated manifest lives at:

```text
storage/app/uat/scenario-pack.json
```

That file is local runtime evidence and should not be committed. See:

- [local login accounts](./docs/runbooks/local-login-accounts.md)
- [UAT scenario pack](./docs/runbooks/uat-demo-scenario-pack.md)

## Troubleshooting

### `composer bootstrap:booking` cannot connect to MySQL

- Confirm MySQL is listening on `.env` `DB_HOST:DB_PORT`.
- Confirm `DB_USERNAME`, `DB_PASSWORD`, and `DB_DATABASE`.
- If using Docker local fallback, set `DB_PASSWORD=123456` unless you changed the compose environment.
- If `mysql` is not on `PATH`, set `MYSQL_BIN`.
- Run:

```bash
mysql --protocol=tcp -h 127.0.0.1 -P 3306 -u root -p -e "SELECT 1"
```

### MySQL helper rejects the runtime

Use MySQL Server 8 compatible `mysqld.exe`. The Windows helper deliberately rejects incompatible local MySQL/MariaDB binaries because the release contract is MySQL 8 oriented.

### Redis is unavailable

Start Redis and confirm:

```bash
redis-cli -h 127.0.0.1 -p 6379 ping
```

Windows helper:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\ops\start-local-redis.ps1 -Restart
```

### Scheduler heartbeat is stale

Keep this running:

```bash
php artisan schedule:work
```

Or restart the managed runtime:

```bash
npm run runtime:restart
```

Then rerun:

```bash
php artisan booking:doctor --json
```

### Browser calls are blocked by CORS

Set `CORS_ALLOWED_ORIGINS` in backend `.env` to exact frontend origins. Use `http://127.0.0.1:3000`, `http://localhost:3000`, `http://127.0.0.1:5173`, and `http://localhost:5173` as needed. Do not use wildcards in production-like environments.

### Runtime preflight fails after a fresh pull

Run:

```bash
composer install
php artisan config:clear
composer bootstrap:booking
npm run runtime:restart
npm run runtime:preflight
```

### Ports are already in use

Defaults:

- backend: `8000`
- customer-web: `3000`
- staff-web: `5173`
- MySQL: `3306`
- Redis: `6379`

Stop the existing process, change `.env` ports, or pass explicit ports to the frontend dev commands.

## Repository Map

- `app/`: Laravel application and domain modules
- `routes/`: API and console route registration
- `config/`: runtime, release, auth, feature flag, and booking configuration
- `database/schema/mysql-schema.sql`: canonical MySQL schema dump
- `database/patches/`: release patch inventory
- `tools/mysql/`: SQL-first bootstrap, backup, restore, and verification helpers
- `scripts/ops/`: local runtime and preflight helpers
- `scripts/ci/`: CI gate scripts
- `scripts/uat/`: local UAT scenario pack helpers
- `staff-web/`: staff React/Vite client
- `customer-web/`: customer Next.js client
- `build/api-consumer/`: generated consumer artifacts
- `docs/runbooks/`: operator, release, API, UAT, and runtime documentation
- `storage/app/booking_release/`: frozen release evidence and reports

## Important Runbooks

- [Windows local setup](./docs/runbooks/booking-local-windows-vscode-cmd-runbook.md)
- [SQL release bootstrap](./database/README_release_bootstrap.md)
- [MySQL bootstrap helper](./tools/mysql/README_bootstrap_release.md)
- [API contract](./docs/runbooks/booking-api-contract.md)
- [API consumer artifacts](./docs/runbooks/api-consumer-artifacts.md)
- [Launch readiness](./docs/runbooks/booking-launch-readiness.md)
- [CI/CD](./docs/runbooks/booking-ci-cd-runbook.md)
- [Release packaging](./docs/runbooks/booking-release-packaging-runbook.md)
- [Deploy](./docs/runbooks/booking-deploy-runbook.md)
- [Backup and restore](./docs/runbooks/booking-backup-restore-runbook.md)
- [Disaster recovery drill](./docs/runbooks/booking-disaster-recovery-drill.md)

## Contribution Standards

Before opening a PR:

1. Keep changes aligned with the SQL-first bootstrap and release contract.
2. Prefer focused, reviewable batches.
3. Keep controllers thin and move business behavior into services or domain logic.
4. Add or update tests for meaningful behavior changes.
5. Run the smallest verification set that proves the change.
6. Update docs and runbooks when operator behavior, bootstrap, runtime, artifacts, or deployment steps change.
7. Fill the PR template with intent, changed files, tests, verification, and remaining risks.

Project standards:

- [CONTRIBUTING.md](./CONTRIBUTING.md)
- [SECURITY.md](./SECURITY.md)
- [PR template](./.github/PULL_REQUEST_TEMPLATE.md)
- [Issue templates](./.github/ISSUE_TEMPLATE/)

## Security

Do not open public issues for exploitable vulnerabilities. Use [SECURITY.md](./SECURITY.md) and GitHub Security Advisories for responsible disclosure.

## License

This repository is licensed under MIT. See [LICENSE](./LICENSE).
