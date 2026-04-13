# Staff-Web Live Smoke

- Decision: `block`
- Target: `local`
- Mode: `read-only`
- API: `http://127.0.0.1:8000/api/v1`
- Preview: `preview`
- Preview status: `not-configured`

## Steps

| Step | Status | Detail |
| --- | --- | --- |
| backend health | FAIL | 503 health=fail redis=redis_unavailable; scheduler=scheduler_heartbeat_missing ttl=180s |
