# Feature Flag Defaults

Default conservatively. A public env flag should not turn a blocked backend contract into a live production dependency.

| Flag | Default | Reason |
|---|---:|---|
| `NEXT_PUBLIC_FEATURE_MENU_CATEGORIES` | false | Categories may be fallback-only. |
| `NEXT_PUBLIC_FEATURE_MENU_ITEM_DETAIL` | false | Detail routes may be fallback-only. |
| `NEXT_PUBLIC_FEATURE_TABLE_AVAILABILITY` | false | Availability can affect reservation correctness. |
| `NEXT_PUBLIC_FEATURE_TABLE_HOLDS` | false | Holds require strong session and expiry semantics. |
| `NEXT_PUBLIC_FEATURE_WAITING_LIST` | false | Owner/session behavior must be clear first. |
| `NEXT_PUBLIC_FEATURE_VOUCHERS` | false | Apply/remove flows require stable mutation contracts. |
| `NEXT_PUBLIC_FEATURE_PRIVACY_REQUESTS` | false | Privacy actions need stable owner and lifecycle behavior. |
| `NEXT_PUBLIC_FEATURE_DATA_EXPORT` | false | Export shape and availability need stable contract evidence. |

## Enabling Rule

Enable by default only after `restaurantpos-customer-web-contract-router` classifies the feature `stable/live` and tests protect the boundary.
