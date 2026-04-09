# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/codex-parallel-agent-prompts.md`
- `README.md`

## Shared files to protect

- `routes/api.php`
- `config/booking.php`
- `config/staff_capabilities.php`
- `database/schema/mysql-schema.sql`

## Recommended workstreams

- Auth and RBAC hardening
- FOH and reservation hardening
- Order lifecycle hardening
- Checkout and finance hardening
- Kitchen and KDS hardening
- Inventory and purchasing hardening
- API contract and gate updates
- Ops and release-contract sync
- Final integration pass

## Questions to answer before splitting work

- Which priority bucket from `AGENTS.md` does the request belong to?
- Which files can each batch own without conflict?
- Which tests prove each batch in isolation?
- Which shared seams need a later integration batch?
