[CmdletBinding()]
param(
    [string]$ManifestPath = 'storage/app/uat/scenario-pack.json',
    [switch]$KeepManifest,
    [switch]$PassThru
)

$ErrorActionPreference = 'Stop'

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))

Push-Location $repoRoot
try {
    $arguments = @('artisan', 'booking:uat-pack:reset', "--manifest-path=$ManifestPath", '--json')
    if ($KeepManifest) {
        $arguments += '--keep-manifest'
    }

    $output = & php @arguments 2>&1 | Out-String

    if ($LASTEXITCODE -ne 0) {
        throw "booking:uat-pack:reset failed.`n$output"
    }

    $payload = $output | ConvertFrom-Json
    if (-not $payload.ok) {
        throw "booking:uat-pack:reset returned a non-ok payload.`n$output"
    }

    if ($PassThru) {
        $payload.data
        return
    }

    Write-Host "UAT scenario pack reset completed." -ForegroundColor Green
    Write-Host "Manifest: $($payload.data.manifest_path)"
    Write-Host "Deleted manifest: $($payload.data.manifest_deleted)"

    $deleted = @($payload.data.deleted.PSObject.Properties | Where-Object { [int]$_.Value -gt 0 })
    if ($deleted.Count -gt 0) {
        Write-Host "Touched tables:"
        foreach ($row in $deleted) {
            Write-Host ("  - {0}: {1}" -f $row.Name, $row.Value)
        }
    }

    Write-Host "Next:"
    Write-Host '  - Rebuild the canonical UAT pack with `powershell -ExecutionPolicy Bypass -File scripts\\uat\\Bootstrap-UatPack.ps1` before live verification.'
    Write-Host '  - `npm run dev:all` also refreshes the same manifest for the standard local lane.'
}
finally {
    Pop-Location
}
