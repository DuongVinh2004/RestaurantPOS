# Form Patterns

## Standard Form

- Define a Zod schema.
- Infer form values from the schema.
- Use React Hook Form resolver.
- Render label, control, helper text, and field error together.
- Submit through a feature mutation hook.

## Server Validation

- Preserve typed field errors from the normalized API error.
- Attach known field errors to form fields.
- Show unknown validation errors in an inline alert.

## Submit Button

Text states:

- Idle: action verb, such as `Sign in` or `Pay bill`.
- Pending: short progress text, such as `Signing in...`.
- Disabled: keep visible reason nearby, not only disabled styling.

## Dangerous Actions

Use confirmation UI for cancellation or destructive account actions. Do not use a basic dialog for destructive confirmation if an alert dialog primitive exists.
