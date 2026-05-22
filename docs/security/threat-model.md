# Threat Model
- Customer auth token/session risks: Token theft, CSRF on web client.
- Staff API key/cookie/CSRF risks: Staff session hijacked or API key leaked.
- Payment webhook spoofing/replay risks: Malicious actors firing fake webhooks.
- Branch data leakage: Staff from Branch A reading Branch B orders.
- Reservation abuse/rate-limit risks: Bot mass-booking tables.
- Audit log sensitive data leakage: PII or payment tokens saved in logs.
- Inventory/reporting unauthorized access: Lower tier staff viewing high-level metrics.
