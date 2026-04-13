param(
    [ValidateSet('up', 'down', 'restart')]
    [string] $Action = 'up'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $false

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$envFilePath = Join-Path $repoRoot '.env'
$logsDir = Join-Path $repoRoot 'storage\logs'
$backendStdOutLog = Join-Path $logsDir 'local-runtime-backend.log'
$backendStdErrLog = Join-Path $logsDir 'local-runtime-backend.err.log'
$schedulerStdOutLog = Join-Path $logsDir 'local-runtime-scheduler.log'
$schedulerStdErrLog = Join-Path $logsDir 'local-runtime-scheduler.err.log'
$startMySqlScript = Join-Path $repoRoot 'scripts\ops\start-local-mysql.ps1'
$startRedisScript = Join-Path $repoRoot 'scripts\ops\start-local-redis.ps1'
$artisanPath = (Resolve-Path (Join-Path $repoRoot 'artisan')).Path
$artisanRelativePath = 'artisan'
$normalizedRepoRoot = ([System.IO.Path]::GetFullPath($repoRoot)).Replace('\', '/').ToLowerInvariant()
$normalizedArtisanPath = ([System.IO.Path]::GetFullPath($artisanPath)).Replace('\', '/').ToLowerInvariant()
$normalizedMySqlDataMarkers = @(
    (Join-Path $repoRoot 'storage\mysql-local\data').Replace('\', '/').ToLowerInvariant(),
    'c:/rp/storage/mysql-local/data'
)
$normalizedRedisConfigMarkers = @(
    (Join-Path $repoRoot 'tools\redis\local-dev-redis.conf').Replace('\', '/').ToLowerInvariant(),
    'tools/redis/local-dev-redis.conf'
)
$backendProcessPattern = '(?<!\S)serve(?!\S)'
$schedulerProcessPattern = '(?<!\S)schedule:work(?!\S)'

function Read-DotEnvFile {
    param(
        [string] $Path
    )

    $values = @{}

    if (-not (Test-Path $Path)) {
        return $values
    }

    foreach ($rawLine in Get-Content $Path) {
        $line = $rawLine.Trim()

        if ($line -eq '' -or $line.StartsWith('#')) {
            continue
        }

        if ($line.StartsWith('export ')) {
            $line = $line.Substring(7).Trim()
        }

        $separatorIndex = $line.IndexOf('=')
        if ($separatorIndex -lt 1) {
            continue
        }

        $key = $line.Substring(0, $separatorIndex).Trim()
        $value = $line.Substring($separatorIndex + 1).Trim()

        if (
            ($value.StartsWith('"') -and $value.EndsWith('"')) -or
            ($value.StartsWith('''') -and $value.EndsWith(''''))
        ) {
            $value = $value.Substring(1, $value.Length - 2)
        } elseif ($value -match '^(.*?)(\s+#.*)$') {
            $value = $Matches[1].TrimEnd()
        }

        if ($key -ne '') {
            $values[$key] = $value
        }
    }

    return $values
}

function Get-ConfigValue {
    param(
        [hashtable] $Values,
        [string] $Key,
        [string] $Default = ''
    )

    $envValue = [Environment]::GetEnvironmentVariable($Key)
    if (-not [string]::IsNullOrWhiteSpace($envValue)) {
        return $envValue
    }

    if ($Values.ContainsKey($Key) -and -not [string]::IsNullOrWhiteSpace([string] $Values[$Key])) {
        return [string] $Values[$Key]
    }

    return $Default
}

function Normalize-CommandLine {
    param(
        [string] $Value
    )

    return ([string] $Value).Replace('\', '/').ToLowerInvariant()
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

function Get-PhpExecutable {
    $command = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    throw 'php.exe was not found. Install the PHP CLI and add it to PATH before using the local runtime script.'
}

function Resolve-BackendServeConfig {
    param(
        [hashtable] $Values
    )

    $defaultHost = '127.0.0.1'
    $defaultPort = 8000
    $appUrl = Get-ConfigValue -Values $Values -Key 'APP_URL' -Default 'http://127.0.0.1:8000'
    $serveHost = $defaultHost
    $servePort = $defaultPort

    if (-not [string]::IsNullOrWhiteSpace($appUrl)) {
        try {
            $uri = [System.Uri] $appUrl

            if (-not [string]::IsNullOrWhiteSpace($uri.Host)) {
                $serveHost = $uri.Host
            }

            if ($appUrl -match '^[a-z]+://[^/]+:\d+(?:/|$)' -and $uri.Port -gt 0) {
                $servePort = $uri.Port
            }
        } catch {
            $serveHost = $defaultHost
            $servePort = $defaultPort
        }
    }

    $baseUrl = "http://$serveHost`:$servePort"

    return @{
        Host = $serveHost
        Port = $servePort
        BaseUrl = $baseUrl
        HealthUrl = "$baseUrl/api/v1/health"
    }
}

function Get-RepoPhpProcesses {
    param(
        [string] $CommandPattern
    )

    return @(
        Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
            Where-Object {
                if ($_.Name -notin @('php.exe', 'php') -or -not $_.CommandLine) {
                    return $false
                }

                $normalizedCommandLine = Normalize-CommandLine -Value $_.CommandLine

                return (
                    $normalizedCommandLine.Contains($script:normalizedArtisanPath) -or
                    $normalizedCommandLine -match '(?<!\S)artisan(?=\s|$)'
                ) -and ($normalizedCommandLine -match $CommandPattern)
            }
    )
}

function Test-RepoBackendProcess {
    param(
        $ProcessInfo
    )

    if (-not $ProcessInfo -or $ProcessInfo.Name -notin @('php.exe', 'php') -or -not $ProcessInfo.CommandLine) {
        return $false
    }

    $normalizedCommandLine = Normalize-CommandLine -Value $ProcessInfo.CommandLine

    return $normalizedCommandLine.Contains($script:normalizedRepoRoot) -and $normalizedCommandLine.Contains('server.php')
}

function Get-RepoBackendProcesses {
    return @(
        Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
            Where-Object {
                Test-RepoBackendProcess -ProcessInfo $_
            }
    )
}

function Get-RepoMySqlProcesses {
    return @(
        Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
            Where-Object {
                if ($_.Name -ne 'mysqld.exe' -or -not $_.CommandLine) {
                    return $false
                }

                $normalizedCommandLine = Normalize-CommandLine -Value $_.CommandLine

                return @($script:normalizedMySqlDataMarkers | Where-Object { $normalizedCommandLine.Contains($_) }).Count -gt 0
            }
    )
}

function Get-RepoRedisProcesses {
    return @(
        Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
            Where-Object {
                if ($_.Name -ne 'redis-server.exe' -or -not $_.CommandLine) {
                    return $false
                }

                $normalizedCommandLine = Normalize-CommandLine -Value $_.CommandLine

                return @($script:normalizedRedisConfigMarkers | Where-Object { $normalizedCommandLine.Contains($_) }).Count -gt 0
            }
    )
}

function Test-RepoPhpProcess {
    param(
        $ProcessInfo,
        [string] $CommandPattern
    )

    if (-not $ProcessInfo -or $ProcessInfo.Name -notin @('php.exe', 'php') -or -not $ProcessInfo.CommandLine) {
        return $false
    }

    $normalizedCommandLine = Normalize-CommandLine -Value $ProcessInfo.CommandLine

    return (
        $normalizedCommandLine.Contains($script:normalizedArtisanPath) -or
        $normalizedCommandLine -match '(?<!\S)artisan(?=\s|$)'
    ) -and ($normalizedCommandLine -match $CommandPattern)
}

function Wait-ForProcessExit {
    param(
        [scriptblock] $ResolveProcesses,
        [int] $Attempts = 20,
        [int] $DelayMs = 500
    )

    for ($attempt = 1; $attempt -le $Attempts; $attempt++) {
        if ((@(& $ResolveProcesses)).Count -eq 0) {
            return $true
        }

        Start-Sleep -Milliseconds $DelayMs
    }

    return $false
}

function Wait-ForPortState {
    param(
        [int] $TargetPort,
        [bool] $ExpectListening,
        [int] $Attempts = 30,
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

function Wait-ForHealthEndpoint {
    param(
        [string] $Url,
        [int] $ProcessId,
        [int] $Attempts = 40,
        [int] $DelayMs = 500,
        [int] $TimeoutSec = 3
    )

    for ($attempt = 1; $attempt -le $Attempts; $attempt++) {
        if (-not (Get-Process -Id $ProcessId -ErrorAction SilentlyContinue)) {
            return $false
        }

        try {
            $response = Invoke-WebRequest -Uri $Url -Method Get -UseBasicParsing -TimeoutSec $TimeoutSec
            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400) {
                return $true
            }
        } catch {
            # keep waiting while the server boots
        }

        Start-Sleep -Milliseconds $DelayMs
    }

    return $false
}

function Reset-LogFiles {
    param(
        [string[]] $Paths
    )

    foreach ($path in $Paths) {
        Set-Content -Path $path -Value '' -Encoding ascii
    }
}

function Stop-RepoPhpProcesses {
    param(
        [string] $Label,
        [string] $CommandPattern,
        [int] $TargetPort = 0
    )

    $processIdsToStop = [System.Collections.Generic.HashSet[int]]::new()

    if ($TargetPort -gt 0) {
        $existingConnection = Get-ListeningConnection -TargetPort $TargetPort
        if ($existingConnection) {
            $ownerProcess = Get-ProcessInfo -ProcessId $existingConnection.OwningProcess
            if ($ownerProcess -and (Test-RepoPhpProcess -ProcessInfo $ownerProcess -CommandPattern $CommandPattern)) {
                [void] $processIdsToStop.Add([int] $ownerProcess.ProcessId)
            }
        }
    }

    foreach ($process in (Get-RepoPhpProcesses -CommandPattern $CommandPattern)) {
        [void] $processIdsToStop.Add([int] $process.ProcessId)
    }

    if ($processIdsToStop.Count -eq 0) {
        return $false
    }

    foreach ($processId in ($processIdsToStop | Sort-Object -Descending)) {
        Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
    }

    if ($TargetPort -gt 0) {
        if (-not (Wait-ForPortState -TargetPort $TargetPort -ExpectListening $false -Attempts 30 -DelayMs 500)) {
            throw "$Label did not release port $TargetPort after the stop signal."
        }
    }

    if (-not (Wait-ForProcessExit -ResolveProcesses { Get-RepoPhpProcesses -CommandPattern $CommandPattern } -Attempts 20 -DelayMs 500)) {
        throw "$Label processes were signalled to stop, but at least one repo-local process is still running."
    }

    return $true
}

function Stop-RepoBackendProcesses {
    param(
        [int] $TargetPort
    )

    $processIdsToStop = [System.Collections.Generic.HashSet[int]]::new()

    $existingConnection = Get-ListeningConnection -TargetPort $TargetPort
    if ($existingConnection) {
        $ownerProcess = Get-ProcessInfo -ProcessId $existingConnection.OwningProcess
        if ($ownerProcess -and (Test-RepoBackendProcess -ProcessInfo $ownerProcess)) {
            [void] $processIdsToStop.Add([int] $ownerProcess.ProcessId)

            if ($ownerProcess.ParentProcessId -gt 0) {
                $parentProcess = Get-ProcessInfo -ProcessId $ownerProcess.ParentProcessId
                if ($parentProcess -and $parentProcess.Name -in @('php.exe', 'php')) {
                    [void] $processIdsToStop.Add([int] $parentProcess.ProcessId)
                }
            }
        }
    }

    foreach ($process in (Get-RepoBackendProcesses)) {
        [void] $processIdsToStop.Add([int] $process.ProcessId)

        if ($process.ParentProcessId -gt 0) {
            $parentProcess = Get-ProcessInfo -ProcessId $process.ParentProcessId
            if ($parentProcess -and $parentProcess.Name -in @('php.exe', 'php')) {
                [void] $processIdsToStop.Add([int] $parentProcess.ProcessId)
            }
        }
    }

    if ($processIdsToStop.Count -eq 0) {
        return $false
    }

    foreach ($processId in ($processIdsToStop | Sort-Object -Descending)) {
        Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
    }

    if (-not (Wait-ForPortState -TargetPort $TargetPort -ExpectListening $false -Attempts 30 -DelayMs 500)) {
        throw "Backend server did not release port $TargetPort after the stop signal."
    }

    if (-not (Wait-ForProcessExit -ResolveProcesses { Get-RepoBackendProcesses } -Attempts 20 -DelayMs 500)) {
        throw 'Backend server processes were signalled to stop, but at least one repo-local backend process is still running.'
    }

    return $true
}

function Test-HealthEndpoint {
    param(
        [string] $Url,
        [int] $TimeoutSec = 3
    )

    try {
        $response = Invoke-WebRequest -Uri $Url -Method Get -UseBasicParsing -TimeoutSec $TimeoutSec
        return $response.StatusCode -ge 200 -and $response.StatusCode -lt 400
    } catch {
        return $false
    }
}

function Start-BackendProcess {
    param(
        [string] $PhpExecutable,
        [hashtable] $ServeConfig
    )

    $existingConnection = Get-ListeningConnection -TargetPort $ServeConfig.Port
    if ($existingConnection) {
        $ownerProcess = Get-ProcessInfo -ProcessId $existingConnection.OwningProcess
        if (-not (Test-RepoBackendProcess -ProcessInfo $ownerProcess)) {
            $ownerLabel = if ($ownerProcess) { "$($ownerProcess.Name) (PID $($ownerProcess.ProcessId))" } else { "PID $($existingConnection.OwningProcess)" }
            throw "Port $($ServeConfig.Port) is already in use by $ownerLabel. Stop that process manually before starting the repo-local backend."
        }

        if (Test-HealthEndpoint -Url $ServeConfig.HealthUrl -TimeoutSec 3) {
            return $ownerProcess
        }
    }

    [void] (Stop-RepoBackendProcesses -TargetPort $ServeConfig.Port)
    Reset-LogFiles -Paths @($backendStdOutLog, $backendStdErrLog)

    $backendProcess = Start-Process `
        -FilePath $PhpExecutable `
        -ArgumentList @($artisanRelativePath, 'serve', "--host=$($ServeConfig.Host)", "--port=$($ServeConfig.Port)") `
        -WorkingDirectory $repoRoot `
        -RedirectStandardOutput $backendStdOutLog `
        -RedirectStandardError $backendStdErrLog `
        -PassThru

    if (-not (Wait-ForPortState -TargetPort $ServeConfig.Port -ExpectListening $true -Attempts 40 -DelayMs 500)) {
        throw "Backend server did not start listening on $($ServeConfig.BaseUrl). Inspect $backendStdErrLog for details."
    }

    if (-not (Wait-ForHealthEndpoint -Url $ServeConfig.HealthUrl -ProcessId $backendProcess.Id -Attempts 40 -DelayMs 500 -TimeoutSec 3)) {
        throw "Backend server started on port $($ServeConfig.Port), but $($ServeConfig.HealthUrl) did not become healthy. Inspect $backendStdOutLog and $backendStdErrLog."
    }

    return $backendProcess
}

function Start-SchedulerProcess {
    param(
        [string] $PhpExecutable
    )

    [void] (Stop-RepoPhpProcesses -Label 'Scheduler worker' -CommandPattern $script:schedulerProcessPattern)
    Reset-LogFiles -Paths @($schedulerStdOutLog, $schedulerStdErrLog)

    $schedulerProcess = Start-Process `
        -FilePath $PhpExecutable `
        -ArgumentList @($artisanRelativePath, 'schedule:work') `
        -WorkingDirectory $repoRoot `
        -RedirectStandardOutput $schedulerStdOutLog `
        -RedirectStandardError $schedulerStdErrLog `
        -PassThru

    Start-Sleep -Milliseconds 1500

    if (-not (Get-Process -Id $schedulerProcess.Id -ErrorAction SilentlyContinue)) {
        throw "Scheduler worker exited immediately. Inspect $schedulerStdErrLog for details."
    }

    return $schedulerProcess
}

function Touch-SchedulerHeartbeat {
    param(
        [string] $PhpExecutable
    )

    $heartbeatOutput = & $PhpExecutable $artisanPath 'booking:ops-heartbeat:touch' 'scheduler' '--json' 2>&1
    if ($LASTEXITCODE -ne 0) {
        $message = (($heartbeatOutput | ForEach-Object { $_.ToString() }) -join [Environment]::NewLine).Trim()
        if ($message -eq '') {
            $message = 'booking:ops-heartbeat:touch exited with a non-zero code.'
        }

        throw "Scheduler heartbeat could not be primed. $message"
    }
}

function Start-LocalRuntime {
    param(
        [string] $PhpExecutable,
        [hashtable] $ServeConfig
    )

    if (-not (Test-Path $startMySqlScript)) {
        throw "MySQL runtime script not found: $startMySqlScript"
    }

    if (-not (Test-Path $startRedisScript)) {
        throw "Redis runtime script not found: $startRedisScript"
    }

    & $startMySqlScript
    & $startRedisScript

    $backendProcess = Start-BackendProcess -PhpExecutable $PhpExecutable -ServeConfig $ServeConfig
    $schedulerProcess = Start-SchedulerProcess -PhpExecutable $PhpExecutable
    Touch-SchedulerHeartbeat -PhpExecutable $PhpExecutable

    Write-Output "Local runtime is up."
    Write-Output "MySQL: repo-local runtime ensured on 127.0.0.1:3306."
    Write-Output "Redis: repo-local runtime ensured on 127.0.0.1:6379."
    Write-Output "Backend: $($ServeConfig.BaseUrl) (PID $($backendProcess.Id))."
    Write-Output "Scheduler: running with PID $($schedulerProcess.Id)."
    Write-Output "Logs: backend -> $backendStdOutLog ; scheduler -> $schedulerStdOutLog"
    Write-Output 'Next: run `npm run runtime:preflight` when you want the full doctor/outbox gate.'
}

function Stop-LocalRuntime {
    $schedulerStopped = Stop-RepoPhpProcesses -Label 'Scheduler worker' -CommandPattern $schedulerProcessPattern
    $backendStopped = Stop-RepoBackendProcesses -TargetPort $serveConfig.Port

    if ((Get-RepoRedisProcesses).Count -gt 0 -and (Test-Path $startRedisScript)) {
        & $startRedisScript -Stop
    }

    if ((Get-RepoMySqlProcesses).Count -gt 0 -and (Test-Path $startMySqlScript)) {
        & $startMySqlScript -Stop
    }

    if ($backendStopped -or $schedulerStopped) {
        Write-Output 'Repo-local backend runtime stopped.'
    } else {
        Write-Output 'No repo-local backend or scheduler process was running.'
    }
}

New-Item -ItemType Directory -Force -Path $logsDir | Out-Null
$phpExecutable = Get-PhpExecutable
$envValues = Read-DotEnvFile -Path $envFilePath
$serveConfig = Resolve-BackendServeConfig -Values $envValues

switch ($Action) {
    'up' {
        Start-LocalRuntime -PhpExecutable $phpExecutable -ServeConfig $serveConfig
        break
    }
    'down' {
        Stop-LocalRuntime
        break
    }
    'restart' {
        Stop-LocalRuntime
        Start-LocalRuntime -PhpExecutable $phpExecutable -ServeConfig $serveConfig
        break
    }
}

$global:LASTEXITCODE = 0
exit 0
