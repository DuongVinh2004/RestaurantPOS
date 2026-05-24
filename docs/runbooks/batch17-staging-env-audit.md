# Batch 17 — Staging Environment & Secret Audit

This document reports the status (PRESENT/MISSING) of critical environment variables for **Batch 17 - Real Staging Infrastructure Setup Checklist & Execution Prep**.

## 1. Audit Executive Summary
- **Overall Verdict**: **PROVIDER_MISSING**
- **Evidence Checked UTC**: `2026-05-24T11:31:26.895Z`
- **Allow Missing Provider Env**: `true`

> [!IMPORTANT]
> This report only shows variable **presence** (PRESENT/MISSING). Secret values are never printed, logged, or checked in plain text.

## 2. Env Presence Breakdown

### CORE RUNTIME
| Environment Variable | Status | Critical |
|---|---|---|
| `APP_ENV` | **PRESENT** | `true` |
| `APP_KEY` | **PRESENT** | `true` |
| `APP_URL` | **PRESENT** | `true` |

### DATABASE
| Environment Variable | Status | Critical |
|---|---|---|
| `DB_CONNECTION` | **PRESENT** | `true` |
| `DB_HOST` | **PRESENT** | `true` |
| `DB_PORT` | **PRESENT** | `true` |
| `DB_DATABASE` | **PRESENT** | `true` |
| `DB_USERNAME` | **PRESENT** | `true` |
| `DB_PASSWORD` | **PRESENT** | `true` |

### REDIS
| Environment Variable | Status | Critical |
|---|---|---|
| `REDIS_HOST` | **PRESENT** | `true` |
| `REDIS_PORT` | **PRESENT** | `true` |

### QUEUE
| Environment Variable | Status | Critical |
|---|---|---|
| `QUEUE_CONNECTION` | **PRESENT** | `true` |

### SCHEDULER
| Environment Variable | Status | Critical |
|---|---|---|
| `SCHEDULER_HEARTBEAT_TTL_SECONDS` | **PRESENT** | `true` |

### MOMO
| Environment Variable | Status | Critical |
|---|---|---|
| `PAYMENT_PROVIDER_MOMO_PARTNER_CODE` | **MISSING** | `false` |
| `PAYMENT_PROVIDER_MOMO_ACCESS_KEY` | **MISSING** | `false` |
| `PAYMENT_PROVIDER_MOMO_SECRET_KEY` | **MISSING** | `false` |
| `PAYMENT_PROVIDER_MOMO_API_URL` | **MISSING** | `false` |

### VNPAY
| Environment Variable | Status | Critical |
|---|---|---|
| `PAYMENT_PROVIDER_VNPAY_TMN_CODE` | **MISSING** | `false` |
| `PAYMENT_PROVIDER_VNPAY_HASH_SECRET` | **MISSING** | `false` |
| `PAYMENT_PROVIDER_VNPAY_PAY_URL` | **MISSING** | `false` |

### WEBHOOK URL
| Environment Variable | Status | Critical |
|---|---|---|
| `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET` | **PRESENT** | `false` |
| `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_SIGNING_SECRET` | **PRESENT** | `false` |

### STAFF/CUSTOMER AUTH
| Environment Variable | Status | Critical |
|---|---|---|
| `STAFF_API_KEY` | **PRESENT** | `true` |
| `CUSTOMER_AUTH_JWT_SECRET` | **PRESENT** | `true` |

