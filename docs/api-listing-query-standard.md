# API Listing Query Standard

## Intent

This project uses a shared listing/query contract for high-value list endpoints so admin/staff clients can reuse the same filter, sort, and pagination behavior across domains.

## Canonical Query Parameters

- `filter[{key}]`
  - Canonical filter surface.
  - Only allowlisted keys are accepted per endpoint.
- `sort`
  - Canonical sort surface.
  - Use `field` for ascending and `-field` for descending.
  - Only allowlisted sort fields are accepted per endpoint.
- `page`
  - 1-based page number for paginated endpoints.
- `per_page`
  - Page size for paginated endpoints.
  - Max page size is published in `meta.query_contract.pagination.max_per_page`.

## Response Meta

Paginated endpoints return:

```json
{
  "meta": {
    "filters": {
      "status": "Confirmed"
    },
    "sort": {
      "supported": true,
      "value": "-start_time",
      "by": "start_time",
      "dir": "desc"
    },
    "pagination": {
      "mode": "paged",
      "current_page": 1,
      "per_page": 25,
      "from": 1,
      "to": 25,
      "total": 120,
      "last_page": 5,
      "has_more_pages": true
    },
    "query_contract": {
      "parameters": {
        "filter": "filter[{key}]",
        "sort": "sort",
        "page": "page",
        "per_page": "per_page"
      },
      "filter_keys": ["status", "q"],
      "sort_fields": ["start_time", "updated_at"],
      "default_sort": "-start_time",
      "pagination": {
        "supported": true,
        "max_per_page": 100
      },
      "legacy_aliases": {
        "status": "filter[status]",
        "sort_by": "sort",
        "sort_dir": "sort"
      }
    },
    "current_page": 1,
    "per_page": 25,
    "from": 1,
    "to": 25,
    "total": 120,
    "last_page": 5,
    "has_more_pages": true
  }
}
```

Legacy full-list endpoints that were not historically paginated now return:

- `meta.pagination.mode = "legacy_unbounded"` when the client omits `page/per_page`
- `meta.pagination.mode = "paged"` when the client opts into pagination

Non-paginated operational surfaces such as timeline/board return:

- `meta.pagination.mode = "none"`
- `meta.query_contract.pagination.supported = false`

## Legacy Compatibility

The following aliases are still accepted on migrated endpoints:

- flat filter params like `status=Confirmed`, `bucket=all`, `q=abc`
- legacy sort params `sort_by` and `sort_dir`

New clients should prefer `filter[...]` and `sort`.

## Anti-footgun Rules

- sort fields are allowlisted per endpoint
- filter keys are allowlisted per endpoint
- free-text `LIKE` filters escape wildcard characters before querying
- paginated endpoints cap `per_page`
- non-paginated endpoints explicitly declare that pagination is unsupported
- timeline keeps a hard validation cap on date range size

## Endpoint Coverage In This Batch

- Staff:
  - `GET /api/v1/staff/reservations`
  - `GET /api/v1/staff/reservations/timeline`
  - `GET /api/v1/staff/waiting-list`
  - `GET /api/v1/staff/tables/board`
  - `GET /api/v1/staff/finance/reconciliation`
  - `GET /api/v1/staff/reporting/daily-sales`
  - `GET /api/v1/staff/reporting/daily-operations`
  - `GET /api/v1/staff/reporting/daily-inventory`
- Admin:
  - `GET /api/v1/admin/menu/categories`
  - `GET /api/v1/admin/menu/items`
  - `GET /api/v1/admin/menu/items/{item_id}/prices`
  - `GET /api/v1/admin/inventory/ingredients`
  - `GET /api/v1/admin/inventory/ingredients/{ingredient_id}/movements`
  - `GET /api/v1/admin/inventory/suppliers`
  - `GET /api/v1/admin/inventory/purchase-orders`

## Examples

Reservation inbox:

```text
/api/v1/staff/reservations?filter[bucket]=all&filter[status]=Confirmed&sort=-guest_count&page=1&per_page=25
```

Waiting list paged view:

```text
/api/v1/staff/waiting-list?filter[active_only]=1&sort=guest_name&page=1&per_page=20
```

Admin menu items:

```text
/api/v1/admin/menu/items?filter[category_id]=10&filter[is_available]=1&sort=code&page=1&per_page=50
```

Finance reconciliation:

```text
/api/v1/staff/finance/reconciliation?filter[reservation_code]=RSV-2026&filter[has_discrepancy]=1&sort=-last_payment_activity_at&page=1&per_page=25
```
