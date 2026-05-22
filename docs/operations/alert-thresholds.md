# Alert Thresholds
- **Payment failure spike**: > 5% failures in 5m (Severity: High)
- **Kitchen ticket backlog**: > 20 tickets pending > 15m (Severity: Medium)
- **Outbox backlog**: > 100 pending (Severity: Low)
- **Scheduler stale**: Heartbeat age > 5m (Severity: Critical)
- **Redis unavailable**: PING fails > 30s (Severity: Critical)
- **MySQL unavailable**: Connections drop (Severity: Critical)
- **High 5xx rate**: > 1% in 5m (Severity: High)
