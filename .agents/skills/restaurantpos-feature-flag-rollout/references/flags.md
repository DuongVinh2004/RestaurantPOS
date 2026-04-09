# Flags

## Current registered flags

- `customer.bill_self_payment`
- `waiting_list.advanced_automation`
- `staff.kitchen_dispatch`
- `inventory.uplift`
- `staff.conversation_inbox`

## Code hotspots

- `config/feature_flags.php`
- `app/Models/FeatureFlag.php`
- `app/Services/FeatureFlagService.php`
- `app/Services/FeatureFlagManagementService.php`

## Test surface

- `tests/Feature/Console/FeatureFlagConsoleCommandTest.php`
- `tests/Unit/Config/FeatureFlagConfigContractTest.php`
- `tests/Unit/Services/FeatureFlagServiceTest.php`
