# Flags

## Current registered flags

- `customer.bill_self_payment`
- `waiting_list.advanced_automation`
- `staff.kitchen_dispatch`
- `inventory.uplift`
- `staff.conversation_inbox`
- `staff.conversation_ai_assist`

## Code hotspots

- `config/feature_flags.php`
- `app/Platform/FeatureFlags/Domain/Models/FeatureFlag.php`
- `app/Platform/FeatureFlags/Services/FeatureFlagService.php`
- `app/Platform/FeatureFlags/Services/FeatureFlagManagementService.php`

## Test surface

- `tests/Feature/Console/FeatureFlagConsoleCommandTest.php`
- `tests/Unit/Config/FeatureFlagConfigContractTest.php`
- `tests/Unit/Services/FeatureFlagServiceTest.php`
