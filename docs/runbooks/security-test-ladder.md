# Security Test Ladder

Use this focused ladder when auth, RBAC, branch isolation, or customer self-service access changes need a fast release-gate signal without running the full suite.

```bash
composer test:security
```

Equivalent direct command:

```bash
php artisan test --filter=SecurityAuthRbacBranchIsolationLadderTest
```

The ladder covers:

- Staff invalid API key denial.
- Staff environment key fallback blocked in production-like config.
- Staff role mismatch denial.
- Missing staff capability denial.
- Unknown capability behavior under `STAFF_CAPABILITIES_ENFORCE_KNOWN`.
- Staff Branch A denied from Branch B reservation detail on the shared customer route.
- Staff Branch A denied from releasing or probing Branch B table state.
- Customer denied from another customer's reservation detail.
- Session-linked hold access kept valid for allowed self-service reservation detail.

For broader release verification, keep the existing targeted filters in the gate:

```bash
php artisan test --filter=StaffProductAuthHttpFlowTest
php artisan test --filter=StaffCapabilityRouteInventoryContractTest
php artisan test --filter=Branch
php artisan test --filter=Reservation
```
