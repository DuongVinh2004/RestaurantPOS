# Inventory/Admin — Backend Contract Map

Generated from backend route/controller/service audit (2026-05-29).

## Feature Gate

```
Feature flag: inventory.uplift
Middleware:   staff.capability:inventory.manage
Auth:         X-Staff-Key header (Admin or Staff role with inventory.manage capability)
```

All routes below require **both** the feature flag enabled and the staff capability.

---

## Ingredients

| Method | Path | Controller | Service | Notes |
|--------|------|-----------|---------|-------|
| GET | `/api/v1/admin/inventory/ingredients` | `InventoryAdjustmentController@listIngredients` | `InventoryManagementService::paginateIngredients` | Filter: `q`, `is_active`, `branch_id`, `page`, `per_page`, `sort` |
| POST | `/api/v1/admin/inventory/ingredients` | `@createIngredient` | `::createIngredient` | Idempotency: `admin.inventory-ingredients.store` |
| GET | `/api/v1/admin/inventory/ingredients/{id}` | `@showIngredient` | `::findIngredient` | Returns with `stock`, `recipe_usage_count` |
| PATCH | `/api/v1/admin/inventory/ingredients/{id}` | `@updateIngredient` | `::updateIngredient` | Row version required |

### Ingredient Create Request
```json
{
  "name": "string (required)",
  "unit_code": "string (required)",
  "code": "string|null",
  "description": "string|null",
  "is_active": "boolean (default: true)"
}
```

### Ingredient Update Request (PATCH)
Same as create. `row_version` required for update.

### Ingredient Resource Shape
```json
{
  "ingredient_id": 1,
  "branch_id": null,
  "code": "ING_TOMATO",
  "name": "Cà chua",
  "unit_code": "kg",
  "description": null,
  "is_active": true,
  "recipe_usage_count": 0,
  "stock": {
    "on_hand": "0.000",
    "branch_id": null
  },
  "row_version": 1,
  "created_at": "ISO8601",
  "updated_at": "ISO8601"
}
```

---

## Stock Movements

| Method | Path | Controller | Notes |
|--------|------|-----------|-------|
| GET | `/api/v1/admin/inventory/ingredients/{id}/movements` | `@listIngredientMovements` | Paginated. Filter: `branch_id`, `movement_type`, `page`, `per_page`, `sort` |
| POST | `/api/v1/admin/inventory/ingredients/{id}/movements` | `@createIngredientMovement` | Creates stock ledger entry. Idempotency: `admin.inventory-movements.store` |

### Stock Movement Types
`StockIn`, `StockOut`, `AdjustmentIncrease`, `AdjustmentDecrease`, `Wastage`

### Movement Create Request
```json
{
  "movement_type": "AdjustmentIncrease",
  "branch_id": 1,
  "quantity": 5.5,
  "unit_code": "kg",
  "reference_type": null,
  "reference_id": null,
  "notes": "Manual count correction"
}
```

### Movement Resource Shape
```json
{
  "movement_id": 1,
  "branch_id": 1,
  "ingredient_id": 1,
  "movement_type": "AdjustmentIncrease",
  "quantity_delta": "5.500",
  "unit_code": "kg",
  "reference": { "type": null, "id": null },
  "notes": "Manual count correction",
  "created_by": 3,
  "created_at": "ISO8601"
}
```

---

## Menu Item Recipes

| Method | Path | Controller | Notes |
|--------|------|-----------|-------|
| GET | `/api/v1/admin/inventory/menu-items/{id}/recipe` | `@showMenuItemRecipe` | Returns recipe lines or empty array |
| PUT | `/api/v1/admin/inventory/menu-items/{id}/recipe` | `@upsertMenuItemRecipe` | Full replace of recipe lines. Idempotency: `admin.inventory-menu-item-recipe.sync` |

### Recipe Upsert Request
```json
{
  "row_version": 0,
  "lines": [
    {
      "ingredient_id": 1,
      "quantity": 0.3,
      "unit_code": "kg",
      "notes": null
    }
  ]
}
```

---

## Suppliers

| Method | Path | Controller | Notes |
|--------|------|-----------|-------|
| GET | `/api/v1/admin/inventory/suppliers` | `ProcurementController@listSuppliers` | Filter: `q`, `is_active`, `page`, `per_page`, `sort` |
| POST | `/api/v1/admin/inventory/suppliers` | `@createSupplier` | Idempotency: `admin.inventory-suppliers.store` |
| GET | `/api/v1/admin/inventory/suppliers/{id}` | `@showSupplier` | Full supplier detail |
| PATCH | `/api/v1/admin/inventory/suppliers/{id}` | `@updateSupplier` | Row version required. Idempotency: `admin.inventory-suppliers.update` |

### Supplier Resource Shape
```json
{
  "supplier_id": 1,
  "code": "SUP-001",
  "name": "Nhà cung cấp A",
  "contact_name": "Nguyễn Văn A",
  "phone": "0901234567",
  "email": "a@example.com",
  "notes": null,
  "is_active": true,
  "row_version": 1,
  "created_at": "ISO8601",
  "updated_at": "ISO8601"
}
```

---

## Purchase Orders

| Method | Path | Controller | Notes |
|--------|------|-----------|-------|
| GET | `/api/v1/admin/inventory/purchase-orders` | `@listPurchaseOrders` | Filter: `q`, `branch_id`, `supplier_id`, `purchase_order_status`, `page`, `per_page`, `sort` |
| POST | `/api/v1/admin/inventory/purchase-orders` | `@createPurchaseOrder` | Idempotency: `admin.inventory-purchase-orders.store` |
| GET | `/api/v1/admin/inventory/purchase-orders/{id}` | `@showPurchaseOrder` | Full PO with `lines[]`, `receipts[]`, `supplier`, `branch` |
| PATCH | `/api/v1/admin/inventory/purchase-orders/{id}` | `@updatePurchaseOrder` | Row version required. Cannot change branch/supplier/lines once receipts exist. Idempotency: `admin.inventory-purchase-orders.update` |

### PO Status Values
`Draft` → `Ordered` → `PartiallyReceived` → `Received` | `Cancelled`

### PO Create Request
```json
{
  "supplier_id": 1,
  "branch_id": null,
  "order_code": null,
  "purchase_order_status": "Ordered",
  "expected_at": null,
  "notes": null,
  "lines": [
    {
      "ingredient_id": 1,
      "ordered_quantity": 10,
      "unit_code": null,
      "unit_cost": null
    }
  ]
}
```

### PO Resource Shape (summary view — list)
```json
{
  "purchase_order_id": 1,
  "branch_id": 1,
  "order_code": "PO-2026-001",
  "purchase_order_status": "Ordered",
  "supplier_id": 1,
  "supplier": { "supplier_id": 1, "code": null, "name": "Supplier A", "is_active": true },
  "ordered_at": "ISO8601",
  "expected_at": null,
  "received_at": null,
  "notes": null,
  "row_version": 1,
  "summary": {
    "line_count": 1,
    "receipt_count": 0,
    "ordered_total_quantity": "10.000",
    "received_total_quantity": "0.000",
    "remaining_total_quantity": "10.000"
  },
  "created_at": "ISO8601",
  "updated_at": "ISO8601"
}
```

---

## Purchase Order Receipts

| Method | Path | Controller | Notes |
|--------|------|-----------|-------|
| GET | `/api/v1/admin/inventory/purchase-orders/{id}/receipts` | `@listPurchaseOrderReceipts` | Paginated receipts for PO |
| POST | `/api/v1/admin/inventory/purchase-orders/{id}/receipts` | `@createPurchaseOrderReceipt` | Posts receipt + creates StockIn movement per line. Idempotency via `receipt_code` + `supplier_document_no` + signature. Idempotency header: `admin.inventory-purchase-order-receipts.store` |

### Receipt Create Request
```json
{
  "receipt_code": null,
  "received_at": null,
  "supplier_document_no": null,
  "notes": null,
  "lines": [
    {
      "purchase_order_line_id": 1,
      "received_quantity": 5.0,
      "unit_code": null,
      "unit_cost": null,
      "notes": null
    }
  ]
}
```

### Receipt Create Business Rules
- PO must be in `Ordered` or `PartiallyReceived` status
- Each `purchase_order_line_id` must belong to the PO
- `received_quantity` must not exceed remaining (`ordered_quantity - received_quantity`) for that line
- Each line may appear once per receipt
- Each posted receipt line creates a `StockIn` stock movement for the ingredient
- PO status auto-updates to `PartiallyReceived` or `Received` after posting

### Receipt Resource Shape
```json
{
  "receipt_id": 1,
  "branch_id": 1,
  "purchase_order_id": 1,
  "receipt_code": "REC-2026-001",
  "receipt_status": "Received",
  "received_at": "ISO8601",
  "supplier_document_no": null,
  "notes": null,
  "summary": {
    "line_count": 1,
    "received_total_quantity": "5.000"
  },
  "created_by": 3,
  "created_at": "ISO8601"
}
```

---

## Key Invariants

| Invariant | Enforcement |
|-----------|-------------|
| Over-receive protection | Validated at line level (`received_quantity > remaining` → 422) |
| Duplicate receipt detection | `receipt_code + supplier_document_no + line_signature` replay detection |
| Row version concurrency | All mutations check `row_version` match before write |
| Branch scope | POs constrained to actor's accessible branches |
| Ingredient distinctness | Each ingredient once per PO; each PO line once per receipt |
| Stock ledger lineage | Receipt `receipt_code:po_line_id` stored as `reference_id` on StockIn movement |

---

## SDK Coverage

| Route | SDK Method | Staff-API Wrapper |
|-------|-----------|-------------------|
| `GET /ingredients` | `getV1AdminInventoryIngredients` | `listAdminIngredients` |
| `POST /ingredients` | (via apiRequest) | `createAdminIngredient` (local api) |
| `PATCH /ingredients/{id}` | (via apiRequest) | `updateAdminIngredient` (local api) |
| `GET /ingredients/{id}/movements` | `getV1AdminInventoryIngredientsIdMovements` | `listAdminIngredientMovements` |
| `POST /ingredients/{id}/movements` | `postV1AdminInventoryIngredientsIdMovements` | `createAdminIngredientMovement` |
| `GET /menu-items/{id}/recipe` | (via apiRequest) | `getAdminMenuItemRecipe` (local api) |
| `PUT /menu-items/{id}/recipe` | (via apiRequest) | `upsertAdminMenuItemRecipe` (local api) |
| `GET /suppliers` | `getV1AdminInventorySuppliers` | `listAdminSuppliers` |
| `POST /suppliers` | (via apiRequest) | `createAdminSupplier` (local api) |
| `PATCH /suppliers/{id}` | (via apiRequest) | `updateAdminSupplier` (local api) |
| `GET /purchase-orders` | `getV1AdminInventoryPurchaseOrders` | `listAdminPurchaseOrders` |
| `POST /purchase-orders` | (via apiRequest) | `createAdminPurchaseOrder` (local api) |
| `GET /purchase-orders/{id}` | (not in SDK) | `showAdminPurchaseOrder` (staff-api.ts via apiRequest) |
| `GET /purchase-orders/{id}/receipts` | `getV1AdminInventoryPurchaseOrdersIdReceipts` | `listAdminPurchaseOrderReceipts` |
| `POST /purchase-orders/{id}/receipts` | `postV1AdminInventoryPurchaseOrdersIdReceipts` | `createAdminPurchaseOrderReceipt` (staff-api.ts) |
