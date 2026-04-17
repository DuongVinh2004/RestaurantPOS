# Query State Patterns

## Query Keys

Use feature namespaces:

```ts
['customer', 'me']
['reservations', 'list']
['reservations', 'detail', reservationId]
['billing', 'active']
['billing', 'payment-session', billId]
['menu', 'items']
```

## States

- Loading: skeleton or compact progress area.
- Empty: explain what is missing and offer the next safe action.
- Error: use normalized message and retry when useful.
- Ready: show primary data and status.
- Refreshing: keep existing data visible and show subtle progress.

## Polling

Use polling for payment or session status only when the backend flow benefits from it. Stop polling on terminal states.
