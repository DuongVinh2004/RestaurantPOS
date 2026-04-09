# Admin Bulk Import/Export

## Scope

Phase 1 supports bulk export/import for:

- `branches`
- `restaurant tables` via the `zone` column on each table row
- `menu categories`
- `menu items`
- `menu prices`
- `vouchers`
- `loyalty tiers`

Phase 1 does not support `ingredients` or `suppliers` yet because those flows are coupled to recipe, stock movement, purchasing, and receiving integrity.

## API Surface

Export:

- `GET /api/v1/admin/settings/branches/export?format=csv|json`
- `GET /api/v1/admin/restaurant/tables/export?format=csv|json`
- `GET /api/v1/admin/menu/categories/export?format=csv|json`
- `GET /api/v1/admin/menu/items/export?format=csv|json`
- `GET /api/v1/admin/menu/prices/export?format=csv|json`
- `GET /api/v1/admin/benefits/vouchers/export?format=csv|json`
- `GET /api/v1/admin/benefits/loyalty-tiers/export?format=csv|json`

Import:

- `POST /api/v1/admin/settings/branches/import`
- `POST /api/v1/admin/restaurant/tables/import`
- `POST /api/v1/admin/menu/categories/import`
- `POST /api/v1/admin/menu/items/import`
- `POST /api/v1/admin/menu/prices/import`
- `POST /api/v1/admin/benefits/vouchers/import`
- `POST /api/v1/admin/benefits/loyalty-tiers/import`

Import request supports:

- `mode`: `dry_run` or `commit`
- `format`: `csv` or `json`
- one input source:
  - `content` for inline CSV/JSON text
  - `rows` for JSON arrays
  - `file` upload

Example JSON dry-run:

```json
{
  "mode": "dry_run",
  "format": "json",
  "rows": [
    {
      "branch_code": "HCM01",
      "branch_name": "Ho Chi Minh 01",
      "timezone": "Asia/Ho_Chi_Minh",
      "currency": "VND",
      "is_active": true,
      "is_default": false
    }
  ]
}
```

## Import Behavior

- Import is `all-or-nothing` on `commit`.
- `dry_run` returns row-level validation and upsert preview without writing data.
- `commit` re-runs validation and returns `422` when `can_commit=false`.
- Replaying the same payload after a successful commit becomes `noop` rows where data is unchanged.
- Duplicate upsert keys inside the same file are rejected.
- Maximum batch size is `500` rows per request.

## Upsert Keys

- `branches`: `branch_code`
- `restaurant tables`: `table_code`
- `menu categories`: `name`
- `menu items`: `code`
- `menu prices`: `item_code + effective_from`
- `vouchers`: `code`
- `loyalty tiers`: `tier_code`

Notes:

- `menu categories` do not have a separate stable business key today, so changing `name` creates a different identity. Category rename-by-import is intentionally not supported.
- `restaurant zones` are not a standalone master-data entity in this schema. Import/export uses the `zone` column on `restaurant_tables`.

## Validation and Reporting

Dry-run and rejected commit responses include:

- `schema.columns`
- `schema.required_columns`
- `schema.errors`
- `summary.total_rows`
- `summary.valid_rows`
- `summary.invalid_rows`
- `summary.create_count`
- `summary.update_count`
- `summary.unchanged_count`
- `rows[].row_number`
- `rows[].match_key`
- `rows[].operation`
- `rows[].errors[]`

## Domain Notes

### Branches

Importable columns:

- `branch_code`
- `branch_name`
- `description`
- `timezone`
- `currency`
- `is_active`
- `is_default`

Not imported:

- `branch_id`
- `row_version`
- `created_at`
- `updated_at`

### Restaurant Tables

Importable columns:

- `branch_code`
- `table_code`
- `template_code`
- `zone`
- `pos_x`
- `pos_y`
- `status`
- `description`
- `price`
- `is_deleted`

Not imported:

- `table_id`
- `branch_id`
- `template_id`
- `row_version`
- runtime read-model fields such as `usage`, `guards`, `capacity`

Status limits:

- allowed in import: `Available`, `Blocked`, `Maintenance`
- intentionally blocked in import: `Reserved`, `Occupied`

### Menu Categories

Importable columns:

- `name`
- `description`
- `sort_order`
- `is_deleted`

Not imported:

- `category_id`

### Menu Items

Importable columns:

- `code`
- `name`
- `category_name`
- `description`
- `img_url`
- `is_available`
- `is_preorder_enabled`
- `preorder_quota_per_day`
- `preorder_cutoff_minutes`

Not imported:

- `item_id`
- `category_id`
- nested `prices`
- computed `current_price`

### Menu Prices

Importable columns:

- `item_code`
- `price`
- `currency`
- `effective_from`
- `effective_to`

Not imported:

- `price_id`
- `item_id`

Note:

- future price-window reconciliation still uses the existing menu pricing service, so adjacent rows may have `effective_to` adjusted by the normal price-window rules during commit.

### Vouchers

Importable columns:

- `code`
- `description`
- `discount_type`
- `discount_value`
- `free_item_code`
- `free_item_qty`
- `max_usage`
- `max_usage_per_user`
- `min_spend`
- `start_date`
- `expiry_date`
- `is_active`

Not imported:

- `voucher_id`
- `free_item_id`
- `created_by`
- `updated_by`
- `row_version`

### Loyalty Tiers

Importable columns:

- `tier_code`
- `tier_name`
- `min_points`
- `benefits_json`
- `is_active`

Not imported:

- `tier_id`
- `row_version`

## Audit and Safety

- Import commit writes a batch audit event: `master_data.import.committed`.
- Entity mutations triggered by import also write per-row audit events for supported services.
- Audit does not store raw uploaded file contents.
- Audit stores batch summary only: domain, format, created/updated/unchanged counts, and affected entity subjects.
- Current import scope avoids customer PII entirely. No phone, email, token, cookie, auth header, or raw file body is logged.

You can inspect import batch audit via the existing staff audit trail surface, for example:

- `GET /api/v1/staff/audit-trail?action=master_data.import.committed`
- `GET /api/v1/staff/audit-trail?subject_type=master_data_domain&subject_id=branches`

## Sample Templates

CSV starter templates live in:

- [branches.csv](/Users/Duong%20Vinh/RestaurantPOS-Laravel/docs/templates/admin-master-data-bulk/branches.csv)
- [restaurant-tables.csv](/Users/Duong%20Vinh/RestaurantPOS-Laravel/docs/templates/admin-master-data-bulk/restaurant-tables.csv)
- [menu-categories.csv](/Users/Duong%20Vinh/RestaurantPOS-Laravel/docs/templates/admin-master-data-bulk/menu-categories.csv)
- [menu-items.csv](/Users/Duong%20Vinh/RestaurantPOS-Laravel/docs/templates/admin-master-data-bulk/menu-items.csv)
- [menu-prices.csv](/Users/Duong%20Vinh/RestaurantPOS-Laravel/docs/templates/admin-master-data-bulk/menu-prices.csv)
- [vouchers.csv](/Users/Duong%20Vinh/RestaurantPOS-Laravel/docs/templates/admin-master-data-bulk/vouchers.csv)
- [loyalty-tiers.csv](/Users/Duong%20Vinh/RestaurantPOS-Laravel/docs/templates/admin-master-data-bulk/loyalty-tiers.csv)
