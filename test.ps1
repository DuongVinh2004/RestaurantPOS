$body = @{
    identifier = 'host01'
    password = 'password'
    device_name = 'Máy phục vụ'
} | ConvertTo-Json

try {
    Invoke-RestMethod -Uri 'http://localhost:8000/api/v1/auth/staff/login' -Method Post -Body $body -ContentType 'application/json' -ErrorAction Stop
} catch {
    Write-Host $_.Exception.Response.StatusCode.value__
    $stream = $_.Exception.Response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($stream)
    Write-Host $reader.ReadToEnd()
}
