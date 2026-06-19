# MySQL & Redis Production-Like Test Lane Runbook

## 1. Purpose & Design Philosophy

SQLite is an excellent tool for quick local unit and integration tests. However, **SQLite hides MySQL locking issues, transaction isolation levels, and Redis cache synchronization gaps**. 

To protect production stability and prevent concurrency bugs (such as double-booking, race conditions, or duplicate payment lines), we define a dedicated **MySQL & Redis Targeted Test Lane**.

### The Two-Lane Testing Strategy
1. **SQLite Lane (Fast Local Feedback)**:
   - *Scope*: All standard unit and feature tests.
   - *Driver*: SQLite (`:memory:`).
   - *Goal*: Sub-minute execution for continuous developer feedback.
   - *Exclusion*: Concurrency and database-specific locking tests (`tests/Feature/Runtime/*`).

2. **MySQL/Redis Lane (Production-Like Verification Gate)**:
   - *Scope*: Targeted concurrency-sensitive tests (locks, checkout, outbox, preorders, vouchers, table holds).
   - *Driver*: MySQL + Redis.
   - *Goal*: 100% accurate simulation of production concurrency behavior.
   - *Enforcement*: Mandatory verification gate before staging deployment or PR merging.

---

## 2. Bootstrapping Strategy (SQL-First)

In alignment with our engineering guidelines, **`php artisan migrate` is not the canonical database schema manager**. The database must be provisioned under our **SQL-first** paradigm:
- Rebuilds the schema using `database/schema/mysql-schema.sql`.
- Applies pending incremental patches from `database/patches/*.sql`.
- Seeds reference data (`SystemReferenceDataSeeder`) and primes necessary local runtime states.

This bootstrap is fully handled by `tools/bootstrap_booking.php` mapped to custom testing configurations.

---

## 3. Targeted Test List

The MySQL/Redis lane runs the following critical, concurrency-sensitive tests:
- **`tests/Feature/Runtime/*`**: Smoke tests specifically verifying:
  - Table contention locks.
  - Redis-backed idempotent replays without duplicate rows.
  - Cashier shift open/close constraints.
  - KDS duplicate dispatch safety.
- **`--filter=TableHold`**: Verifies reservation table locking under load.
- **`--filter=Preorder`**: Verifies customer pre-order sub-total calculations.
- **`--filter=Checkout`**: Verifies billing calculations and final checkout settlement invariants.
- **`--filter=Voucher`**: Verifies voucher eligibility, lock times, and application rules.
- **`--filter=Loyalty`**: Verifies loyalty points calculations and redemption limits.

---

## 4. How to Execute the Lane

Ensure local MySQL and Redis instances are running (e.g., via Docker Loopback or Herd), then invoke the operator scripts:

### Bash (Linux/macOS)
```bash
./scripts/ops/run-mysql-redis-tests.sh
```

### PowerShell (Windows)
```powershell
.\scripts\ops\run-mysql-redis-tests.ps1
```

*Note: The script automatically sets temporary environment variables (`DB_CONNECTION=mysql`, `CACHE_STORE=redis`, etc.) for the execution process without polluting your main `.env` file.*
