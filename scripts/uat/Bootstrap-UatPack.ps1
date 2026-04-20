[CmdletBinding()]
param(
    [string]$BaseUrl = 'http://127.0.0.1:8000',
    [string]$ManifestPath = 'storage/app/uat/scenario-pack.json',
    [switch]$PassThru
)

$ErrorActionPreference = 'Stop'

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))

Push-Location $repoRoot
try {
    $output = & php artisan booking:uat-pack:bootstrap "--base-url=$BaseUrl" "--manifest-path=$ManifestPath" --json 2>&1 | Out-String

    if ($LASTEXITCODE -ne 0) {
        throw "booking:uat-pack:bootstrap failed.`n$output"
    }

    $payload = $output | ConvertFrom-Json
    if (-not $payload.ok) {
        throw "booking:uat-pack:bootstrap returned a non-ok payload.`n$output"
    }

    if ($PassThru) {
        $payload.data
        return
    }

    $summary = $payload.data.summary
    $users = @($summary.users | ForEach-Object { "{0} [{1}]" -f $_.username, $_.role_name })
    $usersLine = if ($users.Count -gt 0) { $users -join ', ' } else { '(none)' }

    Write-Host "UAT scenario pack bootstrapped." -ForegroundColor Green
    Write-Host "Base URL: $BaseUrl"
    Write-Host "Manifest: $($payload.data.manifest_path)"
    Write-Host "Branch  : $($summary.branch.branch_code) - $($summary.branch.branch_name)"
    Write-Host "Users   : $usersLine"
    Write-Host "Scenarios:"
    @($summary.supported_scenarios) | ForEach-Object { Write-Host "  - $_" }
    Write-Host "Next:"
    Write-Host '  - Run `npm run dev:smoke` from the repo root to prove the local lane.'
    Write-Host '  - Run `npm run verify:release:live` from `customer-web` only after Laravel and customer-web are already running.'
}
finally {
    Pop-Location
}
