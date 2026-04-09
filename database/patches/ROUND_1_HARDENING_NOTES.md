# Round 1 hardening patch

This patch applies the first low-conflict hardening round:

1. Blocks legacy customer auth-by-`user_auth_tokens` outside explicitly allowed environments.
2. Requires `row_version` on the critical staff mutation requests audited in round 1.
3. Adds generation-based invalidation for cached table availability reads after hold/reservation/table mutations.

No schema migration is included in this round.
