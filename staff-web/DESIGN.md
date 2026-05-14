# DESIGN.md - RestaurantPOS Staff Web

## 1. Visual Theme and Atmosphere

staff-web uses a light operations UI for Mộc Sen Bistro.

The interface must feel:
- bright
- calm
- professional
- highly scannable
- compact without being cramped
- suitable for long staff sessions

This is not a marketing surface. It is an internal restaurant command center for floor staff, cashiers, kitchen operators, managers, and admins.

Design goal:

> Nhìn là biết việc gì cần xử lý tiếp.

## 2. Product Intent

Design for real restaurant operations:
- table board and floor flow
- reservation check-in and walk-in handling
- order/session context
- kitchen ticket work
- checkout, refund, and cashier shift safety
- manager/admin readiness and audit visibility

Keep day-1 launch promises aligned with `README.md` and `UI_SCOPE.md`. Mounted or contract-visible modules must use honest empty/loading/error states and must not look more complete than their backend/product scope.

## 3. Color Palette and Roles

Base neutrals:
- Page background: `#F6F8FB`
- Elevated surface: `#FFFFFF`
- Secondary surface: `#F1F5F9`
- Muted surface: `#EEF2F7`
- Border: `#D8E0EA`
- Subtle divider: `#E5EAF1`

Text:
- Primary: `#111827`
- Secondary: `#4B5563`
- Tertiary: `#6B7280`
- Disabled: `#9CA3AF`

Actions and status:
- Primary action: `#2563EB`
- Primary hover: `#1D4ED8`
- Secondary accent: `#7C3AED`
- Info: `#0284C7`
- Success: `#16A34A`
- Warning: `#D97706`
- Danger: `#DC2626`

Semantic rules:
- Blue is for primary actions, active navigation, and normal attention.
- Green is for completed, ready, and safe-success states.
- Amber/orange is for soon, late, pending review, or at-risk work.
- Red is for destructive actions, failed states, and blocking issues.
- Purple is a secondary accent only. Do not make it the main product color.
- Do not use a dark theme as the active staff-web direction.

## 4. Typography Rules

Font stack:
- Inter, ui-sans-serif, system-ui, sans-serif

Hierarchy:
- Page title: 24-28px, semibold
- Section title: 18-20px, semibold
- Card title: 15-16px, semibold
- Table header: 12px, semibold
- Body default: 13-14px
- Metadata: 12px
- KPI number: 24-32px, semibold

Rules:
- Keep headings direct and few.
- Avoid hero-scale typography inside operational pages.
- Keep Vietnamese labels short and readable.
- Do not use negative letter spacing.
- Reserve monospace for codes, IDs, and timestamps only.

## 5. Layout Principles

Shell:
- Light sidebar and topbar.
- Sticky navigation and route context.
- Workspace switching, branch switching, refresh, and logout stay predictable.

Page structure:
1. Page header with short operational copy.
2. Filter/action bar.
3. Urgent work or KPI strip when useful.
4. Main workspace content.
5. Drawer/modal for detail or confirmation.

Density:
- Dense enough for busy shifts.
- Touch targets large enough for tablet use.
- Tables and forms must not feel cramped.
- Avoid marketing hero layouts.

## 6. Depth and Elevation

Use white cards with subtle borders and light shadows.

Rules:
- Page background stays soft light, not pure white glare.
- Cards use radius around 8-14px depending on existing component patterns.
- Use shadows sparingly for surface separation.
- Drawers, modals, dropdowns, and popovers need clear elevation without theatrics.
- Do not use decorative orbs, bokeh, or flashy gradients.

## 7. Component Styling

### Navigation
- Light sidebar with clear selected state.
- Active item uses blue emphasis and a visible edge indicator.
- Group labels are subdued.
- Icons must be consistent and not decorative.

### Page Header
- Strong title, concise description.
- Actions stay on the right when space allows.
- Copy must explain operational context, not market the restaurant.

### Buttons
- Primary: blue fill, white text.
- Secondary/default: white surface, visible border.
- Ghost: subtle hover surface.
- Danger: red and deliberate.
- Icon-only buttons require accessible labels.

### Inputs
- White or secondary light surfaces.
- Visible border.
- Blue focus ring.
- Labels remain visible in forms.
- Validation messages are specific and in Vietnamese where staff-facing.

### Tables
- Sticky headers when useful.
- Light header background.
- Clear row hover and selected states.
- Stable columns and readable long text.
- Status and actions must not rely only on color.

### Filters
- Search, date, status, location, channel, payment, and role filters align cleanly.
- Filters should wrap predictably on tablet.
- Primary action remains distinct from filter controls.

### Cards
Use cards for:
- KPIs
- operational summaries
- repeated records
- detail panels

Cards must be:
- structured
- easy to compare
- not nested inside other cards
- not decorative

### Status Badges
- Reuse shared status tone mapping.
- Labels must be accurate Vietnamese.
- Avoid rainbow overload.
- Keep domain-specific states distinct.

### Modals and Drawers
- Drawers for contextual inspection or edit.
- Modals for confirmation or focused workflows.
- Destructive and payment/refund actions need deliberate confirmation and loading state.

### Alerts and Feedback
- Inline alerts for page-specific blockers.
- Toasts for lightweight success/failure.
- Backend conflict, stale-write, idempotency, and permission errors must remain visible and specific.

## 8. Staff-Facing Copy

Tone:
- short
- calm
- professional
- correctly accented Vietnamese
- operational, not promotional

Good examples:
- Cần xử lý ngay
- Bàn đang phục vụ
- Khách sắp đến
- Chưa có ca thu ngân đang mở
- Hóa đơn cần thanh toán
- Món đã sẵn sàng
- Kiểm tra lại thông tin trước khi hoàn tất

Avoid:
- generic English messages
- unaccented Vietnamese
- "No data"
- "Submit successful"
- marketing slogans inside staff-web

## 9. Domain Guidance

Day-1 priority:
- login
- table board
- reservations
- waiting list manual staff operations
- active order
- checkout and refund
- cashier shift
- finance review

Mounted or contract-visible:
- kitchen/KDS
- conversation inbox
- admin home/settings/inventory
- audit trail
- reporting
- access readiness

Mounted modules must stay honest about scope and capability gates.

## 10. Do

- Use the light token system from `src/styles/tokens.css` and `src/styles/theme.ts`.
- Keep `color-scheme: light`.
- Keep Ant Design aligned with staff-web tokens.
- Reuse shared primitives in `src/shared/ui/primitives.tsx`.
- Preserve route, workspace, branch, auth/session, capability, row_version, and Idempotency-Key behavior.
- Make urgent work visually obvious.

## 11. Do Not

- Do not reintroduce a dark active theme.
- Do not hardcode demo operational data.
- Do not add fake restaurant values in frontend code.
- Do not overpromise mounted modules.
- Do not add one-off page color systems.
- Do not weaken production safety flows.
