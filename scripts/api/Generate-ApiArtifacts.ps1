[CmdletBinding()]
param(
    [string]$OutputRoot,
    [string]$SpecPath,
    [string]$UatManifestPath,
    [switch]$RefreshOpenApi,
    [switch]$PassThru
)

$ErrorActionPreference = 'Stop'

function Get-RepoRoot {
    param([string]$ScriptRoot)

    return [System.IO.Path]::GetFullPath((Join-Path $ScriptRoot '..\..'))
}

$repoRoot = Get-RepoRoot -ScriptRoot $PSScriptRoot
$artisan = Join-Path $repoRoot 'artisan'

$arguments = @($artisan, 'booking:api-artifacts:generate', '--json')

if ($RefreshOpenApi) {
    $contractArguments = @($artisan, 'booking:api-contract', '--write', '--json')

    if (-not [string]::IsNullOrWhiteSpace($SpecPath)) {
        $contractArguments += "--path=$SpecPath"
    }

    $contractPayload = & php @contractArguments | ConvertFrom-Json -Depth 100
}

if (-not [string]::IsNullOrWhiteSpace($OutputRoot)) {
    $arguments += "--output-root=$OutputRoot"
}

if (-not [string]::IsNullOrWhiteSpace($SpecPath)) {
    $arguments += "--spec-path=$SpecPath"
}

if (-not [string]::IsNullOrWhiteSpace($UatManifestPath)) {
    $arguments += "--uat-manifest=$UatManifestPath"
}

$payload = & php @arguments | ConvertFrom-Json -Depth 100

if ($RefreshOpenApi) {
    $manifestPayload = & php $artisan 'booking:release-manifest' '--write' '--json' | ConvertFrom-Json -Depth 100
}

if ($PassThru) {
    if ($RefreshOpenApi) {
        $payload | Add-Member -NotePropertyName api_contract -NotePropertyValue $contractPayload -Force
        $payload | Add-Member -NotePropertyName release_manifest -NotePropertyValue $manifestPayload -Force
    }

    $payload
    return
}

Write-Host "API consumer artifacts generated under $($payload.output_root)" -ForegroundColor Green
Write-Host "Spec: $($payload.spec_path)"

foreach ($artifact in $payload.artifacts.PSObject.Properties) {
    Write-Host ("{0}: {1}" -f $artifact.Name, $artifact.Value)
}

Write-Host ("Curated operations: {0}" -f $payload.summary.curated_operation_count)
Write-Host ("Reference operations: {0}" -f $payload.summary.reference_operation_count)
Write-Host ("UAT environment: {0}" -f ($(if ($payload.summary.uat_environment_generated) { 'generated' } else { 'skipped' })))

if ($RefreshOpenApi) {
    Write-Host ("OpenAPI contract refreshed: {0}" -f $contractPayload.path)
    Write-Host ("Release manifest refreshed: {0}" -f $manifestPayload.snapshot_path)
}
