# Batch 17 — Real Payment Provider Callback Execution Runbook

This runbook guides the staging release operator through the execution of end-to-end integration checks with the actual MoMo Sandbox and VNPay Sandbox networks, utilizing the public webhook URL and secure env secrets.

---

## 1. MoMo Sandbox Integration Check

### Preconditions & Verification
1. **Public HTTPS Webhook URL Active**:
   Ensure Option A, B, or C tunnel is active. Check with `curl` to confirm.
2. **MoMo Dashboard Setup Complete**:
   Confirm that the public Webhook URL matches the configured Notify URL on the MoMo Developer Sandbox Portal.
3. **Environment Audit Pass**:
   Ensure `npm run e2e:staging-env-audit` passes with all critical and MoMo keys marked `PRESENT`.
   - Never use `--allow-staging-blocked` for real staging checks!

### Execution Steps
1. Execute the MoMo Sandbox Callback Smoke test targeting the live staging URL:
   ```bash
   API_BASE_URL=https://<your-public-staging-url>/api/v1 npm run e2e:momo-sandbox-callback-smoke
   ```
2. The script will:
   - Perform a customer authentication.
   - Request a new payment session on the backend.
   - Log the payment request parameters.
   - Wait for the live callback packet to resolve.

### Expected Outcomes
- **Webhook Packet Delivery**: A POST request is delivered from the MoMo gateway IP to `/api/v1/payments/providers/generic_http_hmac/webhooks`.
- **Signature Audits**: The application validates the high-entropy signature from the `X-Payment-Signature` header against the payload and the sandbox merchant secret key.
- **Reservation Reconciliation**: The reservation status transitions cleanly to `Confirmed` (or `Paid`) and deposit logs are updated in the database.
- **Verification Exit**: The CLI exits with code `0` (`PASS`).

### Failure Cases to Investigate
- **No Callback Received**: Check if port 443 is open, DNS is resolved, or the public webhook URL was misconfigured in the MoMo portal.
- **Invalid Signature (401/403)**: Mismatch between the sandbox merchant secret key on your staging host and the key used on the MoMo Sandbox dashboard.
- **IP Whitelist Reject**: Verify if your staging public IP or tunnel domain was blocked by the merchant gateway.

### Evidence Artifacts
Upon a successful execution, the script generates the following verification evidence:
- **JSON**: `storage/app/booking_release/batch17_momo_real_callback_result.json`
- **Markdown**: `docs/runbooks/batch17-momo-real-callback-result.md`

---

## 2. VNPay Sandbox Integration Check

### Preconditions & Verification
1. **Public Staging Domain Active**:
   Verify DNS and SSL certificates are completely valid.
2. **VNPay Sandbox Portal Setup Complete**:
   Ensure the TMN code, Hash Secret, and Return/IPN URLs are registered exactly on the VNPay Sandbox portal.

### Execution Steps
1. Run the VNPay Sandbox Callback script:
   ```bash
   API_BASE_URL=https://<your-public-staging-url>/api/v1 npm run e2e:vnpay-sandbox-callback-smoke
   ```
2. The script will:
   - Perform customer authentication.
   - Initiate a secure payment redirect URL.
   - Confirm receipt and parsing of redirect parameters from the payment landing page.

### Expected Outcomes
- **Hash Signature Matches**: The secure redirect URL hash is successfully decoded, checked, and matches the local secret.
- **State Transition Reconciled**: The application updates session table rows dynamically based on the verified status parameters from VNPay.
- **Verification Exit**: The CLI returns exit code `0` (`PASS`).

### Evidence Artifacts
Upon a successful execution, the script generates:
- **JSON**: `storage/app/booking_release/batch17_vnpay_real_callback_result.json`
- **Markdown**: `docs/runbooks/batch17-vnpay-real-callback-result.md`
