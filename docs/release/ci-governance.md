# CI/CD Governance
- Pre-merge checks: static analysis, unit tests, artifact freshness check.
- Release-only checks: e2e tests, full runtime preflight, staging health.
- Artifact Freshness: CI will fail if generated artifacts drift from code.
- Failed CI: Fix before merge, no overrides.
