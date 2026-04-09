# Sync Checklist

## Primary files

- `database/schema/mysql-schema.sql`
- `database/patches/*`
- `db_all.sql`
- `database/README_release_bootstrap.md`
- `tools/mysql/bootstrap_release.*`
- `app/Services/DatabaseContractInspector.php`

## Typical follow-up checks

- `tests/Unit/Infrastructure/DatabaseReleaseContractArtifactSyncTest.php`
- `tests/Unit/Support/PortableBookingSchemaParityTest.php`
- `tests/Unit/Config/BookingReleaseRequiredSqlPatchesTest.php`
- `tests/Feature/Console/SiteBootstrapCommandTest.php`
- `tests/Feature/Console/BookingReleaseManifestCommandTest.php`
- `tests/Feature/Console/BookingDeployCheckCommandTest.php`

## Questions to answer before merging

- Which SQL artifact is the source of truth for the new behavior?
- Did the patch list remain ordered and import-safe?
- Does `db_all.sql` need regeneration for this batch?
- Do release bootstrap docs still describe the actual contract?
