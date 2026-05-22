# Known Limitations

This document outlines the current limitations of the RestaurantPOS project. This repository represents a production-oriented implementation, but it is important to be transparent about what is fully operational versus what is simulated or requires further integration.

## Production Status
- **Not a Live Production SaaS**: This application is not currently claimed as a live production SaaS. Real-world edge cases (e.g., massive concurrency, complex multi-branch scaling) may require further stress testing. It should be deployed and monitored in a real restaurant environment before it can claim full production readiness.

## Payment Integrations
- **Payment Provider Flows**: While the data models and checkout workflows support payments, refunds, and cashier shifts, real payment provider flows (e.g., Stripe, local bank webhooks) require concrete sandbox/live integration evidence before a real go-live. Currently, payment capture and webhook ingestion may be simulated or lack full external verification.

## Runtime Safety & Verification
- **Verification Dependency**: Runtime safety guarantees rely heavily on MySQL and Redis-backed verification. Passing the automated SQLite-backed tests (PHPUnit) is useful for rapid feedback but is not sufficient proof of production readiness. The real release contract must be verified using actual MySQL databases and Redis instances (e.g., via `php artisan booking:doctor` and the runtime smoke tests).

## Observability & Operations
- **Metrics and Logging**: The foundation for observability (audit logging, structured logs) is present, but complete production wiring (e.g., integration with Datadog, Prometheus, or Grafana dashboards) may need further implementation. True incident-ready alerting and metric dashboards are still evolving.

## Hardware Integration
- **Receipt Printers and KDS Hardware**: KOT (Kitchen Order Tickets) and receipt printing abstracts currently exist in software. They lack the specialized raw ESC/POS integration needed to talk directly to thermal receipt printers on a local network.
