param(
    [switch] $Restart,
    [switch] $Stop
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if ($Restart -and $Stop) {
    throw 'Use either -Restart or -Stop, not both.'
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$redisDataDir = Join-Path $repoRoot 'storage\redis'
$redisConfig = 'tools\redis\local-dev-redis.conf'
$redisConfigPath = Join-Path $repoRoot $redisConfig
$knownRedisServer = 'C:\ProgramData\chocolatey\lib\redis\tools\redis-server.exe'
$redisPort = 6379
$localConfigMarkers = @(
    (Join-Path $repoRoot $redisConfig).Replace('\', '/').ToLowerInvariant(),
    $redisConfig.Replace('\', '/').ToLowerInvariant()
)

function Get-ListeningConnection {
    param(
        [int] $TargetPort
    )

    return Get-NetTCPConnection -LocalPort $TargetPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
}

function Wait-ForPortState {
    param(
        [int] $TargetPort,
        [bool] $ExpectListening,
        [int] $Attempts = 20,
        [int] $DelayMs = 500
    )

    for ($attempt = 1; $attempt -le $Attempts; $attempt++) {
        $connection = Get-ListeningConnection -TargetPort $TargetPort
        $isListening = $null -ne $connection

        if ($ExpectListening -eq $isListening) {
            return $true
        }

        Start-Sleep -Milliseconds $DelayMs
    }

    return $false
}

function Test-IsLocalRedisProcess {
    param(
        $ProcessInfo
    )

    if (-not $ProcessInfo -or $ProcessInfo.Name -ne 'redis-server.exe' -or -not $ProcessInfo.CommandLine) {
        return $false
    }

    $normalizedCommandLine = $ProcessInfo.CommandLine.Replace('\', '/').ToLowerInvariant()

    return @($script:localConfigMarkers | Where-Object { $normalizedCommandLine.Contains($_) }).Count -gt 0
}

function Get-LocalRedisProcesses {
    return @(
        Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
            Where-Object {
                Test-IsLocalRedisProcess -ProcessInfo $_
            }
    )
}

function Stop-LocalRedis {
    $processIdsToStop = [System.Collections.Generic.HashSet[int]]::new()
    $existingConnection = Get-ListeningConnection -TargetPort $redisPort

    if ($existingConnection) {
        $ownerProcess = Get-CimInstance Win32_Process -Filter "ProcessId = $($existingConnection.OwningProcess)" -ErrorAction SilentlyContinue
        if ($ownerProcess) {
            if ($ownerProcess.Name -ne 'redis-server.exe') {
                throw "Port $redisPort is in use by a non-Redis process (PID $($ownerProcess.ProcessId)). Stop it manually before using the repo-local Redis runtime."
            }

            if (-not (Test-IsLocalRedisProcess -ProcessInfo $ownerProcess)) {
                throw "Port $redisPort is in use by a non-local Redis process. Stop that service manually before using the repo-local Redis runtime."
            }

            [void] $processIdsToStop.Add([int] $ownerProcess.ProcessId)
        }
    }

    foreach ($redisProcess in (Get-LocalRedisProcesses)) {
        [void] $processIdsToStop.Add([int] $redisProcess.ProcessId)
    }

    if ($processIdsToStop.Count -eq 0) {
        return $false
    }

    foreach ($processId in ($processIdsToStop | Sort-Object -Descending)) {
        Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
    }

    if (-not (Wait-ForPortState -TargetPort $redisPort -ExpectListening $false -Attempts 20 -DelayMs 500)) {
        throw "Repo-local Redis processes were signalled to stop, but port $redisPort is still listening."
    }

    return $true
}

New-Item -ItemType Directory -Force -Path $redisDataDir | Out-Null
if (-not (Test-Path $redisConfigPath)) {
    throw "Redis config file not found: $redisConfigPath"
}

$redisServerCommand = Get-Command redis-server.exe -ErrorAction SilentlyContinue
$redisServer = if ($redisServerCommand) {
    $redisServerCommand.Source
} elseif (Test-Path $knownRedisServer) {
    $knownRedisServer
} else {
    throw 'redis-server.exe was not found. Install Redis for Windows or add redis-server.exe to PATH.'
}

if ($Stop) {
    $stopped = Stop-LocalRedis
    if ($stopped) {
        Write-Output "Redis stopped on 127.0.0.1:$redisPort. Data dir: $redisDataDir"
    } else {
        Write-Output "No repo-local Redis process was listening on 127.0.0.1:$redisPort."
    }

    return
}

$existingConnection = Get-ListeningConnection -TargetPort $redisPort
if ($existingConnection) {
    $ownerProcess = Get-CimInstance Win32_Process -Filter "ProcessId = $($existingConnection.OwningProcess)" -ErrorAction SilentlyContinue

    if (-not $ownerProcess) {
        throw "Port $redisPort is already listening, but the owning process could not be resolved."
    }

    if ($ownerProcess.Name -ne 'redis-server.exe') {
        throw "Port $redisPort is already in use by a non-Redis process (PID $($ownerProcess.ProcessId))."
    }

    if (-not (Test-IsLocalRedisProcess -ProcessInfo $ownerProcess)) {
        throw "Port $redisPort is already in use by a non-local Redis process. Stop that service manually before starting the repo-local Redis runtime."
    }

    if (-not $Restart) {
        Write-Output "Redis is already running on 127.0.0.1:$redisPort with PID $($ownerProcess.ProcessId). Data dir: $redisDataDir"
        return
    }
}

if ($Restart) {
    [void] (Stop-LocalRedis)
} elseif ((Get-LocalRedisProcesses).Count -gt 0) {
    [void] (Stop-LocalRedis)
}

$process = Start-Process `
    -FilePath $redisServer `
    -ArgumentList @($redisConfig) `
    -WorkingDirectory $repoRoot `
    -WindowStyle Minimized `
    -PassThru

$redisCliCommand = Get-Command redis-cli.exe -ErrorAction SilentlyContinue
if ($redisCliCommand) {
    $ready = $false
    for ($attempt = 1; $attempt -le 20; $attempt++) {
        $ping = & $redisCliCommand.Source -h 127.0.0.1 -p $redisPort ping 2>$null
        if ($ping -eq 'PONG') {
            $ready = $true
            break
        }

        Start-Sleep -Milliseconds 250
    }

    if (-not $ready) {
        throw "Redis process started with PID $($process.Id), but it did not answer PING on 127.0.0.1:$redisPort."
    }
}

Write-Output "Redis started on 127.0.0.1:$redisPort with PID $($process.Id). Data dir: $redisDataDir"
