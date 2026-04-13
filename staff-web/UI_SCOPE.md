# UI_SCOPE.md - Staff Web

## Core modules to prioritize
1. Dashboard
2. Order management
3. Table / floor management
4. Reservation management
5. Menu / categories / modifiers
6. Inventory / stock movements
7. Kitchen / KDS
8. Customers / loyalty
9. Vouchers / campaigns
10. Staff / roles / permissions
11. Reports / analytics
12. Settings / integrations / audit

## Shared primitives required
- AppShell
- SidebarNav
- TopBar
- PageHeader
- ActionBar
- FiltersBar
- SearchInput
- SegmentedTabs
- DataTable
- StatusBadge
- SummaryCard
- KPIGrid
- EmptyState
- InlineAlert
- ConfirmDialog
- SideDrawer
- FormField
- FormSection
- DateRangePicker
- Pagination
- BulkActionBar

## Expected page behavior
- list pages should prioritize filters, search, status, and bulk actions
- detail pages should support timeline/context/side actions cleanly
- create/edit flows should use structured forms, not scattered inputs
- destructive actions should be separated clearly
- dashboard should support quick operational visibility

## Quality bar
- no duplicate tables with slightly different styling
- no inconsistent button heights
- no inconsistent spacing across modules
- no page-specific color systems
- no inaccessible contrast in dark mode
