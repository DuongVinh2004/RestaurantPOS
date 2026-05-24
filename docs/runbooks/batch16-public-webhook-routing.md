# Batch 16 — Local Webhook Routing Setup

This document outlines the setup, path verification, and local signature security checks for the webhook routing interface in **Batch 16 - Staging Infrastructure Execution**.

## 1. Webhook Route Details (Local Verification Scope Only)

The application hosts a webhook receiver at:
- **Local Webhook Endpoint**: `POST http://127.0.0.1:8000/api/v1/payments/providers/{provider_code}/webhooks`
- **Supported Providers (Local)**:
  - `generic_http_hmac` (Local generic HMAC smoke verification)
  - `simulated` (Local dev loopback verification)

> [!WARNING]
> **Real public staging HTTPS URL, external ports, and actual MoMo/VNPay sandbox dashboard callbacks were NOT tested or verified by BATCH 16 execution.**

## 2. Validation & Security Check Verification

We performed connectivity and negative validation testing to ensure that invalid or unsigned callbacks are properly intercepted and blocked locally:

### Negative Case: Missing Signature on `generic_http_hmac`
We sent an unsigned payload to `/api/v1/payments/providers/generic_http_hmac/webhooks` locally:
- **Command Run**:
  ```bash
  curl.exe -i -X POST http://127.0.0.1:8000/api/v1/payments/providers/generic_http_hmac/webhooks -H "Content-Type: application/json" -d '{"test":true}'
  ```
- **Response Status**: `401 Unauthorized`
- **Response Content**:
  ```json
  {"error_code":"invalid_signature","category_code":"invalid_signature","message":"Webhook signature verification failed.",...}
  ```

## 3. Webhook Routing Status
- **Public HTTPS Webhook URL**: **NOT COVERED / NOT VERIFIED**
- **Static IP/IP Whitelists**: **NOT COVERED / NOT VERIFIED**
- **Verdict**: **LOCAL GENERIC WEBHOOK ROUTING CHECK PASS**. Local signatures are strictly enforced; unauthorized callbacks are blocked locally.
