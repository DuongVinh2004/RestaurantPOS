# Interview Talking Points
- SQL-first answer: "We treat the database as a strict contract, patching raw SQL rather than abstracting it away, mirroring real DBA practices."
- Auth vs authorization: "Distinct layers. Auth identifies the token; capability resolution checks branch-level and role-level rights."
- OpenAPI/API artifacts: "Strict typing prevents drift between our Next.js/React frontends and Laravel."
- Runtime preflight: "SQLite tests don't prove Redis locking works. We must verify real services."
- Module ownership: "Domain logic lives in Modules, not Controllers."
