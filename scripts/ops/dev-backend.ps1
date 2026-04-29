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
$envFilePath = Join-Path $repoRoot '.env'
$composeFile = Join-Path $repoRoot 'docker-compose.testing.yml'
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

function Set-PathDirectoryFirst {
    param(
        [string] $Path
    )

    if ([string]::IsNullOrWhiteSpace($Path) -or -not (Test-Path $Path)) {
        return
    }

    $normalizedTargetPath = ([System.IO.Path]::GetFullPath($Path)).TrimEnd('\').ToLowerInvariant()
    $filteredPaths = @(
        $env:PATH -split ';' |
            Where-Object { -not [string]::IsNullOrWhiteSpace($_) } |
            Where-Object {
                try {
                    ([System.IO.Path]::GetFullPath($_)).TrimEnd('\').ToLowerInvariant() -ne $normalizedTargetPath
                } catch {
                    $true
                }
            }
    )

    $env:PATH = (@($Path) + $filteredPaths) -join ';'
}

function Add-KnownWindowsDevToolPaths {
    Add-PathDirectory -Path 'C:\xampp\mysql\bin'
    Add-PathDirectory -Path 'C:\Program Files\MySQL\MySQL Server 8.0\bin'
}

function Get-PhpExecutable {
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
    if ($command) {
        return $command.Source
    }

    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    throw 'php.exe was not found. Install the PHP CLI and add it to PATH before running npm run dev:all.'
}

function Use-PhpExecutable {
    param(
        [string] $PhpExecutable
    )

    $phpDirectory = Split-Path -Path $PhpExecutable -Parent
    Set-PathDirectoryFirst -Path $phpDirectory
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

function Test-LoopbackHost {
    param(
        [string] $Value
    )

    return @('127.0.0.1', 'localhost', '::1') -contains $Value.Trim().ToLowerInvariant()
}

function Test-TcpPort {
    param(
        [string] $TargetHost,
        [int] $TargetPort,
        [int] $TimeoutMs = 1000
    )

    $client = [System.Net.Sockets.TcpClient]::new()

    try {
        $connection = $client.BeginConnect($TargetHost, $TargetPort, $null, $null)
        if (-not $connection.AsyncWaitHandle.WaitOne($TimeoutMs)) {
            return $false
        }

        $client.EndConnect($connection)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Wait-ForTcpService {
    param(
        [string] $TargetHost,
        [int] $TargetPort,
        [int] $Attempts = 60,
        [int] $DelayMs = 500
    )

    for ($attempt = 1; $attempt -le $Attempts; $attempt++) {
        if (Test-TcpPort -TargetHost $TargetHost -TargetPort $TargetPort) {
            return $true
        }

        Start-Sleep -Milliseconds $DelayMs
    }

    return $false
}

function Test-RedisPing {
    param(
        [string] $TargetHost,
        [int] $TargetPort,
        [int] $TimeoutMs = 1000
    )

    $client = [System.Net.Sockets.TcpClient]::new()

    try {
        $connection = $client.BeginConnect($TargetHost, $TargetPort, $null, $null)
        if (-not $connection.AsyncWaitHandle.WaitOne($TimeoutMs)) {
            return $false
        }

        $client.EndConnect($connection)
        $client.ReceiveTimeout = $TimeoutMs
        $client.SendTimeout = $TimeoutMs

        $stream = $client.GetStream()
        $payload = [System.Text.Encoding]::ASCII.GetBytes("*1`r`n`$4`r`nPING`r`n")
        $stream.Write($payload, 0, $payload.Length)

        $buffer = New-Object byte[] 64
        $read = $stream.Read($buffer, 0, $buffer.Length)
        if ($read -le 0) {
            return $false
        }

        $response = [System.Text.Encoding]::ASCII.GetString($buffer, 0, $read)
        return $response.StartsWith('+PONG')
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Wait-ForRedisPing {
    param(
        [string] $TargetHost,
        [int] $TargetPort,
        [int] $Attempts = 60,
        [int] $DelayMs = 500
    )

    for ($attempt = 1; $attempt -le $Attempts; $attempt++) {
        if (Test-RedisPing -TargetHost $TargetHost -TargetPort $TargetPort) {
            return $true
        }

        Start-Sleep -Milliseconds $DelayMs
    }

    return $false
}

function Get-DockerCommand {
    $command = Get-Command docker.exe -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    $command = Get-Command docker -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    return $null
}

function Invoke-NativeCommand {
    param(
        [string] $FilePath,
        [string[]] $Arguments
    )

    $previousErrorActionPreference = $ErrorActionPreference

    try {
        $ErrorActionPreference = 'Continue'
        $output = & $FilePath @Arguments 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    return @{
        ExitCode = $exitCode
        Output = ($output | Out-String).Trim()
    }
}

function Assert-DockerAvailable {
    param(
        [string] $DockerCommand
    )

    $dockerInfo = Invoke-NativeCommand -FilePath $DockerCommand -Arguments @('info', '--format', '{{.ServerVersion}}')
    if ($dockerInfo.ExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($dockerInfo.Output)) {
        throw "Docker is installed but the Docker daemon is not reachable. Start Docker Desktop and retry. $($dockerInfo.Output)"
    }
}

function Wait-ForDockerMySql {
    param(
        [string] $DockerCommand,
        [string] $DbPassword
    )

    $lastOutput = ''

    for ($attempt = 1; $attempt -le 60; $attempt++) {
        $pingOutput = Invoke-NativeCommand -FilePath $DockerCommand -Arguments @('compose', '-f', $composeFile, 'exec', '-T', 'mysql', 'mysqladmin', 'ping', '-h', '127.0.0.1', '-P', '3306', '-u', 'root', "--password=$DbPassword")
        $lastOutput = $pingOutput.Output

        if ($pingOutput.ExitCode -eq 0) {
            return $true
        }

        Start-Sleep -Seconds 1
    }

    throw "Docker MySQL service did not become ready. Last mysqladmin output: $lastOutput"
}

function Start-DockerComposeDependency {
    param(
        [string] $Label,
        [string] $Service,
        [string] $TargetHost,
        [int] $TargetPort,
        [hashtable] $EnvValues
    )

    if (-not (Test-LoopbackHost -Value $TargetHost)) {
        throw "$Label is configured for non-local host $TargetHost`:$TargetPort. Start that external service before using the dev lane."
    }

    if (-not (Test-Path $composeFile)) {
        throw "Docker Compose runtime file is missing: $composeFile"
    }

    $dockerCommand = Get-DockerCommand
    if (-not $dockerCommand) {
        throw 'docker was not found. Install Docker Desktop or start the dependency externally.'
    }

    Assert-DockerAvailable -DockerCommand $dockerCommand

    Write-Host "$Label local helper failed; trying Docker Compose service [$Service]..."
    $composeOutput = Invoke-NativeCommand -FilePath $dockerCommand -Arguments @('compose', '-f', $composeFile, 'up', '-d', $Service)
    if ($composeOutput.ExitCode -ne 0) {
        throw "docker compose up -d $Service failed. $($composeOutput.Output)"
    }

    if (-not (Wait-ForTcpService -TargetHost $TargetHost -TargetPort $TargetPort -Attempts 90 -DelayMs 500)) {
        throw "$Label Docker Compose service [$Service] did not start listening on $TargetHost`:$TargetPort."
    }

    if ($Service -eq 'mysql') {
        $dbPassword = Get-ConfigValue -Values $EnvValues -Key 'DB_PASSWORD' -Default '123456'
        [void] (Wait-ForDockerMySql -DockerCommand $dockerCommand -DbPassword $dbPassword)
    } elseif ($Service -eq 'redis') {
        if (-not (Wait-ForRedisPing -TargetHost $TargetHost -TargetPort $TargetPort -Attempts 60 -DelayMs 500)) {
            throw "$Label Docker Compose service [$Service] is listening on $TargetHost`:$TargetPort but did not answer Redis PING."
        }
    }

    Write-Host "$Label runtime started via Docker Compose service [$Service]."
}

function Invoke-DependencyScriptOrDockerFallback {
    param(
        [string] $Label,
        [string] $ScriptPath,
        [string] $Service,
        [string] $TargetHost,
        [int] $TargetPort,
        [hashtable] $EnvValues,
        [string] $FailureHint
    )

    try {
        & $ScriptPath
    } catch {
        $scriptFailure = $_.Exception.Message

        try {
            Start-DockerComposeDependency -Label $Label -Service $Service -TargetHost $TargetHost -TargetPort $TargetPort -EnvValues $EnvValues
            return
        } catch {
            throw ('{0} runtime could not be ensured. {1} Local helper failed: {2} Docker Compose fallback failed: {3}' -f $Label, $FailureHint, $scriptFailure, $_.Exception.Message)
        }
    }
}

Add-KnownWindowsDevToolPaths
$phpExecutable = Get-PhpExecutable
Use-PhpExecutable -PhpExecutable $phpExecutable
$envValues = Read-DotEnvFile -Path $envFilePath
$dbHost = Get-ConfigValue -Values $envValues -Key 'DB_HOST' -Default '127.0.0.1'
$dbPort = [int] (Get-ConfigValue -Values $envValues -Key 'DB_PORT' -Default '3306')
$redisHost = Get-ConfigValue -Values $envValues -Key 'REDIS_HOST' -Default '127.0.0.1'
$redisPort = [int] (Get-ConfigValue -Values $envValues -Key 'REDIS_PORT' -Default '6379')

if (-not $SkipMySql) {
    if (-not (Test-Path $startMySqlScript)) {
        throw "MySQL runtime script not found: $startMySqlScript"
    }

    Write-Host 'Ensuring MySQL runtime...'
    Invoke-DependencyScriptOrDockerFallback `
        -Label 'MySQL' `
        -ScriptPath $startMySqlScript `
        -Service 'mysql' `
        -TargetHost $dbHost `
        -TargetPort $dbPort `
        -EnvValues $envValues `
        -FailureHint 'Run `npm run mysql:local` (or `npm run mysql:local:restart`), install MySQL Server 8, start a compatible DB externally, or start Docker Desktop for the local Compose fallback.'
}

if (-not $SkipRedis) {
    if (-not (Test-Path $startRedisScript)) {
        throw "Redis runtime script not found: $startRedisScript"
    }

    Write-Host 'Ensuring Redis runtime...'
    Invoke-DependencyScriptOrDockerFallback `
        -Label 'Redis' `
        -ScriptPath $startRedisScript `
        -Service 'redis' `
        -TargetHost $redisHost `
        -TargetPort $redisPort `
        -EnvValues $envValues `
        -FailureHint 'Install Redis for Windows, start a Redis-compatible service externally, or start Docker Desktop for the local Compose fallback.'
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
        & $phpExecutable artisan booking:uat-pack:bootstrap "--base-url=$baseUrl" "--manifest-path=$manifestPath"
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
& $phpExecutable artisan serve "--host=$HostName" "--port=$Port"
exit $LASTEXITCODE
