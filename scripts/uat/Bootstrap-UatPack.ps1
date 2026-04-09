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

    $payload = $output | ConvertFrom-Json -Depth 100
    if (-not $payload.ok) {
        throw "booking:uat-pack:bootstrap returned a non-ok payload.`n$output"
    }

    if ($PassThru) {
        $payload.data
        return
    }

    $summary = $payload.data.summary

    Write-Host "UAT scenario pack bootstrapped." -ForegroundColor Green
    Write-Host "Manifest: $($payload.data.manifest_path)"
    Write-Host "Branch  : $($summary.branch.branch_code) - $($summary.branch.branch_name)"
    Write-Host "Users   : $((@($summary.users) | ForEach-Object { ""$($_.username) [$($_.role_name)]"" }) -join ', ')"
    Write-Host "Scenarios:"
    @($summary.supported_scenarios) | ForEach-Object { Write-Host "  - $_" }
}
finally {
    Pop-Location
}
