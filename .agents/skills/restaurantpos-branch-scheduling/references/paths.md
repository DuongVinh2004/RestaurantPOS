# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/runbooks/branch-scheduling-policy-resolution.md`

## Code hotspots

- `app/Http/Controllers/Api/Admin/AdminBranchController.php`
- `app/Services/Branch/BranchSchedulingPolicyService.php`
- `app/Services/Branch/ReservationBranchScopeService.php`
- `config/booking.php`

## Test surface

- `tests/Unit/Services/Branch/BranchSchedulingPolicyServiceTest.php`
- `tests/Feature/Admin/AdminMultiBranchFoundationHttpFlowTest.php`
- `tests/Feature/Admin/AdminMultiBranchDomainDefaultsHttpFlowTest.php`

## Questions to answer before patching

- Is the branch-local value an override or should it still inherit global defaults?
- Which branch timezone should own the comparison?
- Which downstream flow must change together with the policy rule?
- Did the admin payload or config contract move with the runtime behavior?
