# Staff-Web Route Inventory — Batch 13

This document lists all primary front-of-house (FOH) and administrative pages in the `staff-web` operator client, mapping their paths, authentication requirements, operational dependencies, and UAT test plan status.

---

## Route Inventory

| Page Name | URL/Path | Required Auth | Required Backend Data | Primary API Calls | Expected Loading | Expected Empty | Expected Success | Error/Permission | Priority | UAT Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Login** | `/login` | None | None | `POST /api/v1/staff/auth/token` | None | None | Render login form | Friendly error on invalid credentials | Critical | **Covered** |
| **Dashboard** | `/ops/dashboard` | Staff Session | Shift/Branch Context | `GET /api/v1/staff/pos/shifts/active` | Page spinner | None (Redirects) | Render overview stats & charts | Redirect to `/login` if unauth | Critical | **Covered** |
| **Reservation Inbox** | `/ops/reservations` | Staff Session (`reservation.manage`) | Active/Upcoming Reservations | `GET /api/v1/staff/pos/reservations` | List placeholder | "Không có đặt bàn nào" | Render list of bookings | Permission block/403 block | Critical | **Covered** |
| **Reservation Detail** | `/ops/reservations/:id` | Staff Session (`reservation.manage`) | Specific reservation ID | `GET /api/v1/staff/pos/reservations/:id` | Timeline skeleton | "Không tìm thấy" | Render customer profile & timeline | Permission block/404 handling | Critical | **Covered** |
| **Table Board** | `/ops/tables` | Staff Session (`table.board.view`) | Restaurant Tables list | `GET /api/v1/staff/pos/tables` | Grid spinner | "Không có bàn" | Render grid of tables and states | Permission block/403 block | Critical | **Covered** |
| **POS Ordering** | `/ops/orders` | Staff Session (`order.manage`) | Active service sessions, menu | `GET /api/v1/staff/pos/orders` | Catalog loader | "Trống" / select table | Render menu categories & order basket | Permission block/403 block | Critical | **Covered** |
| **Kitchen Board** | `/kitchen/board` | Staff Session (`kitchen.manage`) | Station tickets | `GET /api/v1/staff/pos/kitchen/tickets` | Ticket skeleton | "Bếp trống" | Render ticket lanes (To Do, Doing) | Permission block/403 block | High | **Covered** |
| **Checkout Settlement** | `/ops/checkout` | Staff Session (`settlement.manage`) | Active order ID | `GET /api/v1/staff/pos/orders/:id/checkout` | Settlement skeleton | "Không có hóa đơn" | Render item summary, tax, subtotal | Permission block/403 block | High | **Covered** |
| **Cashier Shift** | `/ops/cashier-shift` | Staff Session (`cashier.shift.manage`) | Active cash float | `GET /api/v1/staff/pos/shifts/active` | Shift loader | "Chưa mở ca" | Render shift detail, start/end float | Permission block/403 block | High | **Covered** |
| **Finance Review** | `/ops/finance-review` | Staff Session (`settlement.manage`) | Settlement history | `GET /api/v1/staff/pos/settlements` | Table loading | "Không có dữ liệu" | Render settlement grid, filters | Permission block/403 block | Medium | **Covered** |
| **Conversation Inbox** | `/ops/conversations` | Staff Session (`conversation.manage`) | Conversations list | `GET /api/v1/staff/pos/conversations` | Inbox loader | "Chưa có hội thoại" | Render staff inbox list and chat panel | Permission block/403 block | Medium | **Covered** |
| **Admin Settings** | `/admin/settings` | Staff Session (`settings.manage`) | Settings list | `GET /api/v1/staff/pos/settings` | Settings spinner | None | Render configuration sections | Permission block/403 block | Medium | **Covered** |
| **Admin Catalog** | `/admin/catalog` | Staff Session (`menu.manage`) | Categories/Products | `GET /api/v1/staff/pos/products` | Catalog loader | "Danh mục trống" | Render category tree and product list | Permission block/403 block | Medium | **Covered** |
| **Admin Inventory** | `/admin/inventory` | Staff Session (`inventory.manage`) | Stock levels | `GET /api/v1/staff/pos/inventory` | Inventory spinner | "Kho trống" | Render product stock table | Permission block/403 block | Medium | **Covered** |
| **Admin Benefits** | `/admin/benefits` | Staff Session (`voucher.master_data.manage`) | Voucher lists | `GET /api/v1/staff/pos/vouchers` | Vouchers spinner | "Trống" | Render voucher tables | Permission block/403 block | Low | **Covered** |
| **Admin Privacy** | `/admin/privacy` | Staff Session (`privacy.manage`) | Privacy profiles | `GET /api/v1/staff/pos/privacy` | Privacy spinner | "Trống" | Render privacy configurations | Permission block/403 block | Low | **Covered** |
| **Reporting Hub** | `/admin/reporting` | Staff Session (`reporting.view`) | Analytics data | `GET /api/v1/staff/pos/reporting` | Reporting spinner | "Chưa có báo cáo" | Render metrics cards & graphs | Permission block/403 block | Medium | **Covered** |
| **Audit Trail** | `/admin/audit-trail` | Staff Session (`audit.view`) | Audit trail log rows | `GET /api/v1/staff/pos/audit` | Audit table loader | "Trống" | Render operational logs grid | Permission block/403 block | Low | **Covered** |
| **Command Center** | `/ops/command-center` | Staff Session (`reservation.manage`) | Command stats | `GET /api/v1/staff/pos/commands` | Loading shell | "Trống" | Render live terminal and ops controls | Permission block/403 block | Low | **Covered** |

---

## Authentication and Session Model

1. **Tokens**:
   - The application relies on `X-Staff-Key` header authentication configured via React-Query and localized session state managed by `Zustand` store (`useAuthStore`).
   - Session keys are stored in standard localStorage keys, enabling direct injection or simulated authentication by UAT scripts.
2. **Access Controls**:
   - Role-Based Access Control (RBAC) rules gate workspaces (`ops`, `kitchen`, `admin`) via the `<WorkspaceBoundary>` and specific paths via the `<WorkspaceRoute capability={...}>` guard.
   - Unauthorized attempts cleanly redirect to the `/login` shell or display a custom permissions block rather than crashing.
