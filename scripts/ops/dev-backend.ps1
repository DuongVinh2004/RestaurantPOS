param(
    [string] $HostName = '127.0.0.1',
    [int] $Port = 8000,
    [switch] $SkipBootstrap,
    [switch] $SkipRedis,
    [switch] $SkipUatPack,
    [switch] $SkipMySql
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $false

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $repoRoot
$startMySqlScript = Join-Path $repoRoot 'scripts\ops\start-local-mysql.ps1'
$startRedisScript = Join-Path $repoRoot 'scripts\ops\start-local-redis.ps1'
$normalizedRepoRoot = ([System.IO.Path]::GetFullPath($repoRoot)).Replace('\', '/').ToLowerInvariant()

function Add-PathDirectory {
    param(
        [string] $Path
    )

    if ([string]::IsNullOrWhiteSpace($Path) -or -not (Test-Path $Path)) {
        return
    }

    $existingPaths = @($env:PATH -split ';' | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    $normalizedPath = ([System.IO.Path]::GetFullPath($Path)).TrimEnd('\').ToLowerInvariant()
    $alreadyPresent = @(
        $existingPaths |
            Where-Object {
                try {
                    ([System.IO.Path]::GetFullPath($_)).TrimEnd('\').ToLowerInvariant() -eq $normalizedPath
                } catch {
                    $false
                }
            }
    ).Count -gt 0

    if (-not $alreadyPresent) {
        $env:PATH = "$Path;$env:PATH"
    }
}

function Add-KnownWindowsDevToolPaths {
    Add-PathDirectory -Path 'C:\xampp\php'
    Add-PathDirectory -Path 'C:\xampp\mysql\bin'

    $herdLiteBin = Join-Path $env:USERPROFILE '.config\herd-lite\bin'
    Add-PathDirectory -Path $herdLiteBin

    Add-PathDirectory -Path 'C:\Program Files\MySQL\MySQL Server 8.0\bin'
}

function Get-ListeningConnection {
    param(
        [int] $TargetPort
    )

    return Get-NetTCPConnection -LocalPort $TargetPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
}

function Get-ProcessInfo {
    param(
        [int] $ProcessId
    )

    return Get-CimInstance Win32_Process -Filter "ProcessId = $ProcessId" -ErrorAction SilentlyContinue
}

function Normalize-CommandLine {
    param(
        [string] $Value
    )

    if ([string]::IsNullOrWhiteSpace($Value)) {
        return ''
    }

    return ([string] $Value).Replace('\', '/').ToLowerInvariant()
}

function Test-RepoBackendProcess {
    param(
        $ProcessInfo
    )

    if (-not $ProcessInfo -or $ProcessInfo.Name -notin @('php.exe', 'php')) {
        return $false
    }

    $normalizedCommandLine = Normalize-CommandLine -Value $ProcessInfo.CommandLine
    if ($normalizedCommandLine -eq '' -or -not $normalizedCommandLine.Contains($normalizedRepoRoot)) {
        return $false
    }

    return $normalizedCommandLine.Contains('server.php')
}

Add-KnownWindowsDevToolPaths

if (-not $SkipMySql) {
    if (-not (Test-Path $startMySqlScript)) {
        throw "MySQL runtime script not found: $startMySqlScript"
    }

    Write-Host 'Ensuring MySQL runtime...'
    try {
        & $startMySqlScript
    } catch {
        throw ('MySQL runtime could not be ensured. Run `npm run mysql:local` (or `npm run mysql:local:restart`) and confirm MySQL Server 8 is installed. {0}' -f $_.Exception.Message)
    }
}

if (-not $SkipRedis) {
    if (-not (Test-Path $startRedisScript)) {
        throw "Redis runtime script not found: $startRedisScript"
    }

    Write-Host 'Ensuring Redis runtime...'
    & $startRedisScript
}

if (-not $SkipBootstrap) {
    Write-Host 'Bootstrapping booking runtime...'
    & composer bootstrap:booking
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }

    if (-not $SkipUatPack) {
        $baseUrl = "http://$HostName`:$Port"
        $manifestPath = 'storage/app/uat/scenario-pack.json'

        Write-Host "Bootstrapping UAT login/demo data at $manifestPath..."
        & php artisan booking:uat-pack:bootstrap "--base-url=$baseUrl" "--manifest-path=$manifestPath"
        if ($LASTEXITCODE -ne 0) {
            exit $LASTEXITCODE
        }
    }
}

$existingConnection = Get-ListeningConnection -TargetPort $Port
if ($existingConnection) {
    $ownerProcess = Get-ProcessInfo -ProcessId $existingConnection.OwningProcess

    if (-not (Test-RepoBackendProcess -ProcessInfo $ownerProcess)) {
        $ownerLabel = if ($ownerProcess) { "$($ownerProcess.Name) (PID $($ownerProcess.ProcessId))" } else { "PID $($existingConnection.OwningProcess)" }
        throw "Port $Port is already in use by $ownerLabel. Stop that process or pick another backend port before running npm run dev:all."
    }

    Write-Host "Reusing Laravel backend on http://$HostName`:$Port (PID $($ownerProcess.ProcessId))."
    Wait-Process -Id $ownerProcess.ProcessId
    exit 0
}

Write-Host "Starting Laravel backend on http://$HostName`:$Port"
& php artisan serve "--host=$HostName" "--port=$Port"
exit $LASTEXITCODE
