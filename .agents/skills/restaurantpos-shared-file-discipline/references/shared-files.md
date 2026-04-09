# Shared Files

## Highest-risk seams

- `routes/api.php`
  - Collateral checks: auth guards, route names, route surface integrity, OpenAPI artifacts
- `config/booking.php`
  - Collateral checks: runtime settings, reservation and checkout behavior, operational commands
- `config/staff_capabilities.php`
  - Collateral checks: allow and deny path auth tests, route-capability inventory
- `database/schema/mysql-schema.sql`
  - Collateral checks: patches, bootstrap release flow, contract inspector, schema parity tests

## Reporting rule

When any file above changes, report it explicitly under changed files or remaining risks instead of hiding it inside a generic summary.
