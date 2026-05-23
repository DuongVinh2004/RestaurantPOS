# Frontend Runtime Smoke Checklist

## Customer Web
- [x] App builds without errors (`npm --prefix customer-web run build` passed)
- [ ] App loads without blank screen (ENV_BLOCKED: backend down)
- [ ] Home/booking page loads (ENV_BLOCKED)
- [ ] Table search page loads (ENV_BLOCKED)
- [ ] Reservation detail page loads (ENV_BLOCKED)
- [ ] Error banner appears when backend unavailable (Expected YES, manual verify)

## Staff Web
- [x] App builds without errors (`npm --prefix staff-web run build` passed)
- [ ] App loads without blank screen (ENV_BLOCKED: backend down)
- [ ] Login/API key flow works (ENV_BLOCKED)
- [ ] Dashboard page loads (ENV_BLOCKED)
- [ ] Tables page loads (ENV_BLOCKED)
- [ ] Reservation inbox page loads (ENV_BLOCKED)

*Note: Runtime checks are marked ENV_BLOCKED due to the local Windows environment failing to sustain the Redis and HTTP backend.*
