Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $false

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $repoRoot

$npxCommand = Get-Command npx.cmd -ErrorAction SilentlyContinue
if (-not $npxCommand) {
    $npxCommand = Get-Command npx -ErrorAction SilentlyContinue
}

if (-not $npxCommand) {
    throw 'npx was not found. Install Node.js/npm before running npm run dev:all.'
}

$commands = @(
    'powershell -ExecutionPolicy Bypass -File scripts\ops\dev-backend.ps1',
    'powershell -ExecutionPolicy Bypass -File scripts\ops\dev-web.ps1 -App customer-web -HostName 127.0.0.1 -Port 3000',
    'powershell -ExecutionPolicy Bypass -File scripts\ops\dev-web.ps1 -App staff-web -HostName 127.0.0.1 -Port 5173'
)

& $npxCommand.Source concurrently `
    -n 'backend,customer,staff' `
    -c 'blue,green,magenta' `
    --kill-others-on-fail `
    @commands

exit $LASTEXITCODE
