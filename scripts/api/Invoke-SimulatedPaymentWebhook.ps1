[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$BaseUrl,
    [Parameter(Mandatory = $true)]
    [string]$Secret,
    [string]$ProviderCode = 'simulated',
    [ValidateSet('deposit', 'bill')]
    [string]$PaymentScope = 'deposit',
    [string]$ProviderSessionCode = 'sim-dep-001',
    [string]$ProviderEventCode = 'sim-webhook-001',
    [string]$ProviderPaymentCode = 'sim-pay-001',
    [string]$EventType = 'payment.session.updated',
    [string]$SessionStatus = 'Succeeded',
    [string]$OccurredAt = ([DateTime]::UtcNow.ToString('o'))
)

$ErrorActionPreference = 'Stop'

$payload = @{
    payment_scope = $PaymentScope
    provider_session_code = $ProviderSessionCode
    provider_event_code = $ProviderEventCode
    event_type = $EventType
    session_status = $SessionStatus
    provider_payment_code = $ProviderPaymentCode
    occurred_at = $OccurredAt
}

$body = $payload | ConvertTo-Json -Depth 20 -Compress
$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($Secret)
$signatureBytes = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($body))
$signature = ([System.BitConverter]::ToString($signatureBytes)).Replace('-', '').ToLowerInvariant()
$uri = '{0}/api/v1/payments/providers/{1}/webhooks' -f $BaseUrl.TrimEnd('/'), $ProviderCode

$headers = @{
    Accept = 'application/json'
    'Content-Type' = 'application/json'
    'X-Payment-Signature' = $signature
    'X-Payment-Timestamp' = $OccurredAt
}

$response = Invoke-RestMethod -Method 'POST' -Uri $uri -Headers $headers -Body $body -ErrorAction Stop
$response | ConvertTo-Json -Depth 50
