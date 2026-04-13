# DESIGN.md — RestaurantPOS Staff Web

## 1. Visual Theme & Atmosphere
This interface should feel like a modern operational control surface:
- precise
- calm
- highly scannable
- compact without being cramped
- professional rather than flashy

Primary influences:
- Linear for precision and navigation discipline
- Airtable for structured data usability
- IBM for enterprise-grade clarity
- Sentry for dense monitoring surfaces and operational insight

The product is used by staff, managers, cashiers, kitchen operators, and admin users.
The UI must support long sessions and repetitive tasks without visual fatigue.

## 2. Product Intent
This is not a marketing site.
This is an internal operations product.

Design for:
- fast order handling
- clear table status
- confident modifier and menu editing
- inventory awareness
- auditability
- manager-level visibility
- low-friction configuration
- clear exception handling

## 3. Color Palette & Roles
Base neutrals:
- Background: #0F1115
- Surface 1: #151922
- Surface 2: #1B2130
- Surface 3: #232B3B
- Border: #2D3748
- Divider subtle: #202838

Text:
- Primary: #F3F6FB
- Secondary: #BCC7D6
- Tertiary: #8D9AAF
- Disabled: #657289

Primary accent:
- Primary purple: #7C5CFF
- Primary hover: #6F4CFF
- Primary soft: #E9E2FF

Support accents:
- Info blue: #3B82F6
- Success green: #22C55E
- Warning amber: #F59E0B
- Danger red: #EF4444
- Analytics magenta: #D946EF

Semantic rules:
- Purple is for focus, active navigation, key actions, and selected states.
- Blue is for neutral informative states.
- Green is for success and completed operational states.
- Amber is for pending, warning, or at-risk conditions.
- Red is for destructive actions, urgent issues, and blocking failures.
- Magenta can be used in charts and monitoring surfaces, not as the primary product color.

## 4. Typography Rules
Font stack:
- Inter, ui-sans-serif, system-ui, sans-serif

Hierarchy:
- Page title: 28/32, semibold
- Section title: 20/28, semibold
- Card title: 16/24, semibold
- Table header: 12/16, semibold, uppercase optional only in dense data zones
- Body default: 14/20, regular
- Secondary labels: 13/18, medium
- Small metadata: 12/16, regular
- KPI number: 28/32 or 32/36, semibold

Rules:
- Keep heading count low.
- Avoid oversized hero-like typography.
- Favor compact, readable line lengths.
- Use numeric alignment consistently in report and finance screens.

## 5. Layout Principles
Grid:
- Desktop-first operational shell
- Fixed sidebar + flexible content area
- Maximize usable width for tables and filter zones
- Allow modular cards for analytics and status overview

Spacing scale:
- 4, 8, 12, 16, 20, 24, 32

Density:
- Default density should be compact-medium.
- Tables, filter bars, and record forms should use tighter spacing than marketing-style layouts.
- Preserve breathing room around destructive or complex actions.

Page structure:
1. Page header
2. Filter/action bar
3. KPI row or contextual summary if needed
4. Main content region
5. Secondary drawers / dialogs / side-panels

## 6. Depth & Elevation
- Use subtle layering, not glossy effects.
- Surfaces should separate through contrast and border discipline.
- Prefer soft shadows only for overlays.
- Modals and drawers should feel elevated but not floating theatrically.

Shadows:
- Base cards: minimal or none
- Hover card: soft shadow
- Dropdowns / drawers / modals: medium soft shadow

Radius:
- Inputs: 10px
- Buttons: 10px
- Cards: 14px
- Modals: 18px

## 7. Component Stylings

### Navigation
- Sidebar is dark, stable, disciplined.
- Active item uses purple emphasis with strong contrast.
- Group labels are subtle and compact.
- Icons are simple and consistent.
- Avoid oversized nav items.

### Page Header
- Strong title + concise description.
- Right side reserved for actions.
- Breadcrumbs only when structure truly needs them.

### Buttons
Variants:
- Primary: purple fill, white text
- Secondary: dark surface with border
- Ghost: transparent with subtle hover fill
- Danger: red emphasis only when necessary

Rules:
- Button heights should be standardized.
- Icon buttons need clear hover and focus states.
- Avoid too many competing primary actions in one view.

### Inputs
- Dark surfaces with clear border separation
- Strong focus ring in purple
- Labels always visible in forms
- Help text muted but readable
- Validation messaging inline and specific

### Tables
- High priority component
- Sticky headers when useful
- Clear row hover
- Strong alignment and spacing discipline
- Status, badges, and actions should not create visual noise
- Bulk selection and bulk actions should be obvious
- Dense but readable

### Filters
- Search, date, status, location, channel, payment, and role filters should align cleanly
- Filters should not wrap chaotically
- Primary actions stay visually distinct from filter controls

### Cards
Use cards for:
- KPIs
- operational summaries
- side information
- drill-down modules

Cards must feel:
- structured
- compact
- not decorative
- easy to compare side by side

### Status Badges
- Small, readable, semantically consistent
- Avoid rainbow overload
- Reuse the same status color logic across orders, reservations, inventory, and payments

### Modals and Drawers
- Use drawers for contextual edits and inspection
- Use modals for confirmation or focused workflows
- Keep action placement predictable
- Do not overcrowd modal surfaces with dense tables unless necessary

### Alerts and Feedback
- Inline alerts for page-specific issues
- Toasts for lightweight success/failure messages
- Use red sparingly so real urgency remains meaningful

### Charts and Metrics
- Clean axes
- restrained grid lines
- limited color palette
- charts support decisions; they are not decoration

## 8. Interaction Principles
- Every screen should be scannable in seconds.
- Every critical action should be obvious.
- Every destructive action should feel deliberate.
- Keyboard and power-user flow should be respected where possible.
- Empty states should explain what to do next.
- Loading states should preserve layout stability.

## 9. Domain-Specific Guidance
Suitable for:
- dashboard
- orders
- tables
- reservations
- menu and modifiers
- kitchen display
- inventory
- purchase / receiving
- customers and membership
- vouchers / promos
- shifts / staff / permissions
- settlements / finance overview
- audit logs
- system settings

## 10. Do
- build reusable admin primitives first
- favor consistency over novelty
- keep forms structured
- keep table actions disciplined
- use whitespace intentionally
- keep status language consistent
- make reports and operational screens decision-friendly

## 11. Do Not
- do not create giant marketing hero sections
- do not overuse gradients
- do not use multiple competing accent colors
- do not use weak-contrast text
- do not overload every card with icons and badges
- do not create custom styles per page unless they are folded back into shared primitives
