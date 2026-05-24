# Batch 17 — Public Webhook URL Setup Runbook

This runbook describes how to configure, establish, and verify a secure public HTTPS webhook endpoint for receiving live Sandbox callbacks from MoMo and VNPay payment gateways.

---

## 1. Webhook Setup Options

### OPTION A — Real Staging Domain (Recommended)
1. **DNS Mapping**:
   Configure a public DNS A-Record pointing to the staging host IP:
   ```text
   staging.restaurantpos.example.com  IN  A  203.0.113.88
   ```
2. **Reverse Proxy Configuration (Nginx)**:
   Configure a site definition block mapping HTTP requests to the local port (e.g., 8000):
   ```nginx
   server {
       listen 80;
       server_name staging.restaurantpos.example.com;
       return 301 https://$host$request_uri;
   }

   server {
       listen 443 ssl;
       server_name staging.restaurantpos.example.com;

       ssl_certificate /etc/letsencrypt/live/staging.restaurantpos.example.com/fullchain.pem;
       ssl_certificate_key /etc/letsencrypt/live/staging.restaurantpos.example.com/privkey.pem;

       location / {
           proxy_pass http://127.0.0.1:8000;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```
3. **Environment Setup**:
   Update staging env to map exactly:
   ```env
   APP_URL=https://staging.restaurantpos.example.com
   ```
   - Webhook URL format: `https://staging.restaurantpos.example.com/api/v1/payments/providers/generic_http_hmac/webhooks`

---

### OPTION B — Ngrok Tunnel (Fast Track Dev/UAT)
1. **Download and Install Ngrok**:
   Download the ngrok binary and authenticate your account:
   ```bash
   ngrok config add-authtoken <your-ngrok-token>
   ```
2. **Launch Secure Tunnel**:
   Forward the local Laravel dev server port (e.g., 8000):
   ```bash
   ngrok http 8000
   ```
3. **Capture Public HTTPS Address**:
   Copy the generated forwarding URL from the terminal output, e.g.:
   ```text
   https://43ea-203-0-113-88.ngrok-free.app
   ```
4. **Environment Setup**:
   Update environment variable on your staging host:
   ```env
   APP_URL=https://43ea-203-0-113-88.ngrok-free.app
   ```
   - **Important Caveat**: Free ngrok URLs change on every restart. Remember to update both the `.env` file and your merchant sandbox dashboards each time you restart the tunnel.

---

### OPTION C — Cloudflare Tunnel (Recommended for Secure Direct Expose)
1. **Install Cloudflared**:
   Follow instructions on your Cloudflare dashboard to install the `cloudflared` client on your staging host.
2. **Authenticate Staging Host**:
   ```bash
   cloudflared tunnel login
   ```
3. **Create the Tunnel**:
   ```bash
   cloudflared tunnel create staging-pos-tunnel
   ```
4. **Assign DNS Route mapping**:
   Map the tunnel to a permanent subdomain on Cloudflare:
   ```bash
   cloudflared tunnel route dns staging-pos-tunnel staging.restaurantpos.example.com
   ```
5. **Start Tunnel Service**:
   ```bash
   cloudflared tunnel run --url http://localhost:8000 staging-pos-tunnel
   ```

---

## 2. Verification Runbook

### Step 1: Execute Negative Webhook Connectivity Check
Once the tunnel is active, test route exposure by sending a request without a signature. The application signature guard should intercept the call and return a `401 Unauthorized` status (not a `404 Not Found` or `500 Server Error`).

```bash
# Substitute your actual public URL
curl -i -X POST https://<your-public-url>/api/v1/payments/providers/generic_http_hmac/webhooks \
  -H "Content-Type: application/json" \
  -d '{"ping": true}'
```

#### Expected Success Response:
```text
HTTP/2 401
Content-Type: application/json

{"error_code":"invalid_signature","category_code":"invalid_signature","message":"Webhook signature verification failed."}
```

### Step 2: Generate Verification Evidence File
To confirm verification succeeded without staging-blocked triggers, record the details into `storage/app/booking_release/batch17_public_webhook_check.json`.

Example of verification check results script output:
```json
{
  "public_url": "https://staging.restaurantpos.example.com",
  "endpoint": "/api/v1/payments/providers/generic_http_hmac/webhooks",
  "checked_at_utc": "2026-05-24T10:30:00Z",
  "http_status": 401,
  "error_code": "invalid_signature",
  "resolved_ip": "203.0.113.88",
  "connectivity": "PASS"
}
```
Ensure this target is created when connectivity is confirmed by the deploy operator.
