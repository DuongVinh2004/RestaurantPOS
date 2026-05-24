# Batch 16 — Staging Target Confirmation

This document confirms the exact local verification targets for **Batch 16 - Staging Infrastructure Execution**.

## Staging Deployment Specifications (Local Verification Only)

- **Local App URL**: `http://localhost:8000` (Local verification only)
- **Local Backend API URL**: `http://127.0.0.1:8000/api/v1`
- **Local staff-web URL**: `http://localhost:5173`
- **Local customer-web URL**: `http://localhost:3000`
- **Local Webhook URL**: `http://127.0.0.1:8000/api/v1/payments/providers/generic_http_hmac/webhooks`
- **Server OS**: Windows (Local integration target)
- **Runtime Type**: Local verification mimicking staging specifications
- **Database**:
  - **Connection**: `mysql`
  - **Host**: `127.0.0.1:3306`
  - **Database Name**: `restaurantdb`
- **Redis**:
  - **Connection**: `phpredis`
  - **Host**: `127.0.0.1:6379`
- **Queue Worker**:
  - **Driver**: `sync`
- **Scheduler**:
  - **Type**: Manual touching (Long-running staging cron **NOT VERIFIED**)
- **Branch / Commit Deployed**:
  - **Branch**: `harden-frontend-contract-parity`
  - **Commit Hash**: `b3bb19de37fd57b8a69d0d8d580c98d1ae652a3d`
