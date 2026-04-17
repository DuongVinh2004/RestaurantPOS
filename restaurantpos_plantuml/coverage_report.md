# Coverage report (v9)

## Inventory

- Use case: `28` (`23 UC + 5 UCD`)
- Activity: `32`
- Sequence: `24`
- Class: `13`
- ERD: `13`

## Domain coverage matrix

| Domain flow | Use case | Activity | Sequence | Structural diagrams |
| --- | --- | --- | --- | --- |
| Auth / Identity / RBAC | `UC-01`, `UC-02` | `AD-01`, `AD-02` | `SD-01`, `SD-02` | `CD-01`, `ERD-01` |
| Availability / hold / reservation self-service | `UC-03`, `UC-04`, `UC-05`, `UC-06`, `UC-07` | `AD-03..12` | `SD-03..08` | `CD-02`, `CD-03`, `ERD-02`, `ERD-03` |
| Waiting list / FOH / walk-in / table board | `UC-09`, `UC-10`, `UC-11` | `AD-13..17` | `SD-09..11` | `CD-03`, `ERD-03` |
| Order lifecycle / kitchen dispatch | `UC-12`, `UC-13` | `AD-18..20` | `SD-12`, `SD-13` | `CD-04`, `CD-08`, `ERD-04`, `ERD-08` |
| Checkout / refund / cashier shift | `UC-08`, `UC-14`, `UC-15`, `UC-23` | `AD-21..23`, `AD-32` | `SD-08`, `SD-14`, `SD-15`, `SD-24` | `CD-04`, `ERD-04` |
| Voucher / loyalty / benefits | `UC-16` | `AD-24`, `AD-25` | `SD-16` | `CD-05`, `ERD-05` |
| Admin master data / inventory / purchasing / kitchen config | `UC-17`, `UC-18` | `AD-26..28` | `SD-17`, `SD-18` | `CD-06`, `CD-07`, `CD-08`, `ERD-06`, `ERD-07`, `ERD-08` |
| Conversation inbox / notification platform | `UC-19` | `AD-29`, `AD-31` | `SD-19`, `SD-23` | `CD-09`, `CD-12`, `ERD-09`, `ERD-12` |
| Privacy / reporting / ops readiness | `UC-20`, `UC-21`, `UC-22` | `AD-30` | `SD-20`, `SD-21`, `SD-22` | `CD-10`, `CD-11`, `ERD-10`, `ERD-11` |

## Coverage notes

- cashier shift is now covered explicitly instead of only being implied by finance class/ERD slices
- payment webhook ownership stays in `UC-08` and `SD-08`; `UC-22` and `SD-22` are now health/metrics/release only
- notification platform has both structural coverage (`CD-12`, `ERD-12`) and behavioral coverage (`AD-31`, `SD-23`)
