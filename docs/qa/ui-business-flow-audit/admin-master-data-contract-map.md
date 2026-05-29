# Admin Master Data — Contract Map

Batch: `admin-master-data-complete-foundation`
Generated: 2026-05-29

---

## Capability: `settings.manage`

All routes below require the `settings.manage` staff capability. Verified against
`config/staff_capabilities.php`.

---

## Branch API

| Method | Path | Capability | Controller |
|---|---|---|---|
| `GET` | `/api/v1/admin/settings/branches` | `settings.manage` | `BranchController@index` |
| `POST` | `/api/v1/admin/settings/branches` | `settings.manage` | `BranchController@store` |
| `GET` | `/api/v1/admin/settings/branches/{id}` | `settings.manage` | `BranchController@show` |
| `PATCH` | `/api/v1/admin/settings/branches/{id}` | `settings.manage` | `BranchController@update` |
| `GET` | `/api/v1/admin/settings/branches/export` | `settings.manage` | `BranchController@export` |
| `POST` | `/api/v1/admin/settings/branches/import` | `settings.manage` | `BranchController@import` |

### Sample response shape
```json
{
  "data": {
    "branch_id": 1,
    "branch_code": "HN01",
    "branch_name": "Chi nhánh Hà Nội",
    "is_default": true,
    "is_active": true,
    "phone": null,
    "email": null,
    "address": null,
    "timezone": "Asia/Ho_Chi_Minh",
    "currency": "VND",
    "row_version": 1,
    "business_hours": [
      { "day_of_week": 1, "periods": [{ "start_time": "08:00", "end_time": "22:00" }] }
    ],
    "closure_windows": [],
    "booking_policy": {}
  },
  "meta": { "action": "admin_branch_show" }
}
```

---

## Zone API

| Method | Path | Capability | Controller |
|---|---|---|---|
| `GET` | `/api/v1/admin/restaurant/zones` | `settings.manage` | `RestaurantZoneController@index` |
| `POST` | `/api/v1/admin/restaurant/zones/rename` | `settings.manage` | `RestaurantZoneController@rename` |

### Zone rename payload
```json
{ "from": "Khu A", "to": "Khu VIP" }
```

---

## Restaurant Table API

| Method | Path | Capability | Controller |
|---|---|---|---|
| `GET` | `/api/v1/admin/restaurant/tables` | `settings.manage` | `RestaurantTableController@index` |
| `POST` | `/api/v1/admin/restaurant/tables` | `settings.manage` | `RestaurantTableController@store` |
| `GET` | `/api/v1/admin/restaurant/tables/{id}` | `settings.manage` | `RestaurantTableController@show` |
| `PATCH` | `/api/v1/admin/restaurant/tables/{id}` | `settings.manage` | `RestaurantTableController@update` |
| `DELETE` | `/api/v1/admin/restaurant/tables/{id}` | `settings.manage` | `RestaurantTableController@destroy` |
| `GET` | `/api/v1/admin/restaurant/tables/export` | `settings.manage` | `RestaurantTableController@export` |
| `POST` | `/api/v1/admin/restaurant/tables/import` | `settings.manage` | `RestaurantTableController@import` |
| `GET` | `/api/v1/admin/restaurant/table-templates` | `settings.manage` | `RestaurantTableTemplateController@index` |

> **Note:** `DELETE` is guarded by `has_active_operational_links`. Cannot delete tables with active reservations, holds, or orders.

---

## Kitchen Station API

| Method | Path | Capability | Controller |
|---|---|---|---|
| `GET` | `/api/v1/admin/kitchen/stations` | `settings.manage` | `KitchenStationController@index` |
| `POST` | `/api/v1/admin/kitchen/stations` | `settings.manage` | `KitchenStationController@store` |
| `GET` | `/api/v1/admin/kitchen/stations/{station_id}` | `settings.manage` | `KitchenStationController@show` |
| `PATCH` | `/api/v1/admin/kitchen/stations/{station_id}` | `settings.manage` | `KitchenStationController@update` |

---

## Category Route API

| Method | Path | Capability | Controller |
|---|---|---|---|
| `GET` | `/api/v1/admin/kitchen/stations/{station_id}/category-routes` | `settings.manage` | `KitchenCategoryRouteController@index` |
| `PUT` | `/api/v1/admin/kitchen/stations/{station_id}/category-routes` | `settings.manage` | `KitchenCategoryRouteController@update` |

### Category route sync payload
```json
{
  "routes": [
    { "category_id": 1 },
    { "category_id": 3 }
  ]
}
```
> The `PUT` performs a full sync (replace). Send empty `routes: []` to clear all routes.

---

## Tax Profile API

| Method | Path | Capability | Controller |
|---|---|---|---|
| `GET` | `/api/v1/admin/settings/finance/tax-profile` | `settings.manage` | `FinanceTaxProfileController@showTaxProfile` |
| `POST` | `/api/v1/admin/settings/finance/tax-profile` | `settings.manage` | `FinanceTaxProfileController@upsertTaxProfile` |

### Tax profile payload
```json
{
  "tax_rate": 10,
  "service_charge_rate": 5,
  "currency": "VND",
  "is_tax_inclusive": false,
  "notes": null
}
```

> **E2E restore discipline:** After each E2E run that modifies tax profile, immediately restore original values. Current restore confirmed in `AMD_TAX_PROFILE_UPDATED` test.

---

## Frontend API Client Coverage

| Backend Route | staff-api.ts / settings-crud-api.ts |
|---|---|
| `GET /admin/settings/branches` | `listAdminBranches()` (staff-api.ts) |
| `POST /admin/settings/branches` | `createAdminBranch()` (settings-crud-api.ts) ✅ NEW |
| `GET /admin/settings/branches/{id}` | `showAdminBranch()` (settings-crud-api.ts) ✅ NEW |
| `PATCH /admin/settings/branches/{id}` | `updateAdminBranch()` (settings-crud-api.ts) ✅ NEW |
| `GET /admin/restaurant/zones` | `listAdminZones()` (settings-crud-api.ts) ✅ NEW |
| `POST /admin/restaurant/zones/rename` | `renameAdminZone()` (settings-crud-api.ts) ✅ NEW |
| `GET /admin/restaurant/tables` | `listAdminRestaurantTables()` (staff-api.ts) |
| `POST /admin/restaurant/tables` | `createAdminRestaurantTable()` (staff-api.ts) |
| `PATCH /admin/restaurant/tables/{id}` | `updateAdminRestaurantTable()` (settings-crud-api.ts) ✅ NEW |
| `DELETE /admin/restaurant/tables/{id}` | `deleteAdminRestaurantTable()` (settings-crud-api.ts) ✅ NEW |
| `GET /admin/kitchen/stations` | `listAdminKitchenStations()` (settings-crud-api.ts) ✅ NEW |
| `POST /admin/kitchen/stations` | `createAdminKitchenStation()` (settings-crud-api.ts) ✅ NEW |
| `PATCH /admin/kitchen/stations/{station_id}` | `updateAdminKitchenStation()` (settings-crud-api.ts) ✅ NEW |
| `GET /admin/kitchen/stations/{id}/category-routes` | `listAdminCategoryRoutes()` (settings-crud-api.ts) ✅ NEW |
| `PUT /admin/kitchen/stations/{id}/category-routes` | `syncAdminCategoryRoutes()` (settings-crud-api.ts) ✅ NEW |
| `GET /admin/settings/finance/tax-profile` | `getAdminTaxProfile()` (settings-crud-api.ts) ✅ NEW |
| `POST /admin/settings/finance/tax-profile` | `upsertAdminTaxProfile()` (settings-crud-api.ts) ✅ NEW |

---

## data-testid Registry

| testid | Element | Assertions |
|---|---|---|
| `admin-branches-page` | Branch list card | Visible on `/admin/settings` |
| `admin-branch-row` | Branch row button | One per branch |
| `admin-branch-create-button` | Create branch button | Visible for `settings.manage` |
| `admin-branch-form` | Branch form | Appears in modal |
| `admin-branch-code-input` | Branch code field | Required, disabled on update |
| `admin-branch-name-input` | Branch name field | Required |
| `admin-branch-save-button` | Branch submit button | Loading state during mutation |
| `admin-zones-page` | Zone list card | Visible on `/admin/settings` |
| `admin-zone-row` | Zone row item | One per zone |
| `admin-zone-name-input` | Zone new name input | In rename modal |
| `admin-zone-save-button` | Zone rename submit | Loading state |
| `admin-tables-page` | Table list card | Visible |
| `admin-table-row` | Table row button | One per table |
| `admin-table-create-button` | Create table button | Visible |
| `admin-table-form` | Table create inline form | In same page |
| `admin-table-name-input` | Table code input | Required |
| `admin-table-save-button` | Table create submit | Loading state |
| `admin-kitchen-stations-page` | Kitchen station card | Visible |
| `admin-kitchen-station-row` | Station row button | One per station |
| `admin-kitchen-station-create-button` | Create station button | Visible |
| `admin-kitchen-station-form` | Station form | In modal |
| `admin-kitchen-station-name-input` | Station name input | Required |
| `admin-kitchen-station-save-button` | Station submit | Loading state |
| `admin-category-routes-page` | Category routes card | Visible |
| `admin-category-route-category-select` | Category multi-select | In sync form |
| `admin-category-route-save-button` | Sync submit | Loading state |
| `admin-category-route-row` | Route item in side panel | One per route |
| `admin-tax-profile-page` | Tax profile card | Visible |
| `admin-tax-rate-input` | Tax rate input | Number 0-100 |
| `admin-service-charge-input` | Service charge input | Number 0-100 |
| `admin-tax-profile-save-button` | Tax profile submit | Loading state |
| `admin-tax-profile-success` | Success chip | Visible after save |
| `admin-error-alert` | Error paragraph | Visible on mutation failure |
