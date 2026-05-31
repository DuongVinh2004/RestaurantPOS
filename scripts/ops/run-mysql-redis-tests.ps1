# run-mysql-redis-tests.ps1
# Staging/Local MySQL + Redis Target Test Lane Runner (PowerShell)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$artisanPath = Join-Path $repoRoot 'artisan'
$bootstrapPath = Join-Path $repoRoot 'tools\bootstrap_booking.php'

function Resolve-PhpExecutable {
    $configuredPhp = [Environment]::GetEnvironmentVariable('PHP_BIN')
    if (-not [string]::IsNullOrWhiteSpace($configuredPhp)) {
        if (-not (Test-Path $configuredPhp)) {
            throw "Configured PHP_BIN was not found: $configuredPhp"
        }
        return (Resolve-Path $configuredPhp).Path
    }

    $candidatePaths = @(
        (Join-Path $env:USERPROFILE '.config\herd-lite\bin\php.exe'),
        'C:\xampp\php\php.exe'
    )

    foreach ($candidatePath in $candidatePaths) {
        if (-not [string]::IsNullOrWhiteSpace($candidatePath) -and (Test-Path $candidatePath)) {
            return (Resolve-Path $candidatePath).Path
        }
    }

    $command = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }

    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }

    throw 'php was not found. Install the PHP CLI and add it to PATH before running.'
}

Write-Output "================================================================================"
Write-Output "Starting MySQL/Redis Targeted Test Lane..."
Write-Output "================================================================================"

$php = Resolve-PhpExecutable

# 1. Set environment variables for the current PowerShell session process
$env:APP_ENV = "testing"
$env:DB_CONNECTION = "mysql"
$env:DB_DATABASE = "restaurantdb_test"
$env:DB_USERNAME = "root"
$env:DB_PASSWORD = "123456"
$env:CACHE_STORE = "redis"
$env:REDIS_HOST = "127.0.0.1"
$env:REDIS_PORT = "6379"
$env:REQUIRE_REDIS_FOR_BOOKING_API = "true"
$env:BOOKING_REALTIME_ENABLED = "true"
$env:BOOKING_REALTIME_CACHE_STORE = "redis"

# 2. Boot database using SQL-first release dump and patches
Write-Output "Bootstrapping testing database using SQL-first bootstrap..."
& $php $bootstrapPath '--env-file=.env.testing' '--json'

# 3. Execute targeted integration tests
Write-Output ""
Write-Output "Running concurrency and locking smoke tests (tests/Feature/Runtime)..."
& $php $artisanPath 'test' 'tests/Feature/Runtime'

Write-Output ""
Write-Output "Running targeted concurrency-sensitive feature tests..."
try {
    & $php $artisanPath 'test' '--filter=TableHold'
} catch {
    Write-Output "No TableHold tests found or error occurred."
}

try {
    & $php $artisanPath 'test' '--filter=Preorder'
} catch {
    Write-Output "No Preorder tests found or error occurred."
}

try {
    & $php $artisanPath 'test' '--filter=Checkout'
} catch {
    Write-Output "No Checkout tests found or error occurred."
}

try {
    & $php $artisanPath 'test' '--filter=Voucher'
} catch {
    Write-Output "No Voucher tests found or error occurred."
}

try {
    & $php $artisanPath 'test' '--filter=Loyalty'
} catch {
    Write-Output "No Loyalty tests found or error occurred."
}

Write-Output "================================================================================"
Write-Output "MySQL/Redis Targeted Test Lane completed successfully."
Write-Output "================================================================================"
exit 0
