# UI Checklist

Use this before closing a customer-web UI batch.

## Product

- The first screen serves the task directly.
- Copy is customer-facing and non-technical.
- Disabled features explain availability without promising backend behavior.
- Payment and reservation statuses are visible without opening debug details.

## Layout

- Mobile layout works first.
- Primary actions have large tap targets.
- Repeated items have stable dimensions.
- Text does not overflow on small screens.
- Cards are not nested inside cards.

## States

- Loading state exists for async reads.
- Empty state exists for empty collections.
- Error state uses normalized frontend errors.
- Mutation buttons show pending state and prevent duplicate submits.
- Skeletons are used only where they clarify layout.

## Forms

- React Hook Form handles field state.
- Zod validates request shape.
- Labels and errors are accessible.
- Server validation maps back to fields where possible.
