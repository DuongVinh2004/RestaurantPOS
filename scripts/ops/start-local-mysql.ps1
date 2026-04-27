param(
    [switch] $Restart,
    [switch] $Stop,
    [int] $Port
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $false

if ($Restart -and $Stop) {
    throw 'Use either -Restart or -Stop, not both.'
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$envFilePath = Join-Path $repoRoot '.env'
$mysqlDataDir = Join-Path $repoRoot 'storage\mysql-local\data'
$mysqlLogsDir = Join-Path $repoRoot 'storage\logs'
$mysqlRuntimeLog = Join-Path $mysqlLogsDir 'mysql-local-runtime.err'
$repoRootAlias = 'C:\rp'
$mySqlBaseAlias = 'C:\mysql80'
$knownMySqlServer = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe'
$knownMySqlClient = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe'
$knownMySqlClientPaths = @(
    $knownMySqlClient,
    'C:\xampp\mysql\bin\mysql.exe'
)

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

function Normalize-PathMarker {
    param(
        [string] $Path
    )

    return ([System.IO.Path]::GetFullPath($Path)).Replace('\', '/').ToLowerInvariant()
}

function Normalize-CommandLine {
    param(
        [string] $CommandLine
    )

    if ([string]::IsNullOrWhiteSpace($CommandLine)) {
        return ''
    }

    return $CommandLine.Replace('\', '/').ToLowerInvariant()
}

function Ensure-Junction {
    param(
        [string] $LinkPath,
        [string] $TargetPath
    )

    if (Test-Path $LinkPath) {
        $linkItem = Get-Item $LinkPath -Force
        if (-not ($linkItem.Attributes -band [System.IO.FileAttributes]::ReparsePoint)) {
            throw "$LinkPath already exists and is not a junction. Remove it or update the script alias path before starting local MySQL."
        }

        return
    }

    New-Item -ItemType Junction -Path $LinkPath -Target $TargetPath | Out-Null
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

function Get-LocalMySqlProcesses {
    param(
        [string[]] $CommandLineMarkers
    )

    return @(
        Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
            Where-Object {
                if ($_.Name -ne 'mysqld.exe' -or -not $_.CommandLine) {
                    return $false
                }

                $normalizedCommandLine = Normalize-CommandLine -CommandLine $_.CommandLine
                return @($CommandLineMarkers | Where-Object { $normalizedCommandLine.Contains($_) }).Count -gt 0
            }
    )
}

function Wait-ForPortState {
    param(
        [int] $TargetPort,
        [bool] $ExpectListening,
        [int] $Attempts = 40,
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

function Stop-LocalMySql {
    param(
        [int] $TargetPort,
        [string[]] $CommandLineMarkers
    )

    $localProcesses = Get-LocalMySqlProcesses -CommandLineMarkers $CommandLineMarkers
    $existingConnection = Get-ListeningConnection -TargetPort $TargetPort
    $processIdsToStop = [System.Collections.Generic.HashSet[int]]::new()

    if ($existingConnection) {
        $ownerProcess = Get-ProcessInfo -ProcessId $existingConnection.OwningProcess
        if (-not $ownerProcess) {
            throw "Port $TargetPort is in use, but the owning process could not be resolved."
        }

        if ($ownerProcess.Name -ne 'mysqld.exe') {
            throw "Port $TargetPort is in use by a non-MySQL process (PID $($ownerProcess.ProcessId)). Stop it manually before starting local MySQL."
        }

        $ownerMarker = Normalize-CommandLine -CommandLine $ownerProcess.CommandLine
        if ($ownerMarker -eq '') {
            throw "Port $TargetPort is already in use by mysqld.exe, but the owning command line could not be inspected. Stop that MySQL service manually before using -Stop or -Restart."
        }

        if (@($CommandLineMarkers | Where-Object { $ownerMarker.Contains($_) }).Count -eq 0) {
            throw "Port $TargetPort is in use by a non-local MySQL process. Stop that service manually before using the repo-local MySQL runtime."
        }

        [void] $processIdsToStop.Add([int] $ownerProcess.ProcessId)
    }

    foreach ($localProcess in $localProcesses) {
        [void] $processIdsToStop.Add([int] $localProcess.ProcessId)
    }

    if ($processIdsToStop.Count -eq 0) {
        return $false
    }

    foreach ($processId in ($processIdsToStop | Sort-Object -Descending)) {
        Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
    }

    if (-not (Wait-ForPortState -TargetPort $TargetPort -ExpectListening $false -Attempts 30 -DelayMs 500)) {
        throw "Local MySQL processes were signalled to stop, but port $TargetPort is still listening."
    }

    return $true
}

function Get-MySqlCommand {
    param(
        [string] $ExecutableName,
        [string] $ConfiguredPath = '',
        [string[]] $KnownPaths = @()
    )

    if (-not [string]::IsNullOrWhiteSpace($ConfiguredPath) -and (Test-Path $ConfiguredPath)) {
        return $ConfiguredPath
    }

    $command = Get-Command $ExecutableName -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    foreach ($knownPath in $KnownPaths) {
        if (Test-Path $knownPath) {
            return $knownPath
        }
    }

    return $null
}

function Test-MySqlServerBinaryCompatible {
    param(
        [string] $Path
    )

    if ([string]::IsNullOrWhiteSpace($Path) -or -not (Test-Path $Path)) {
        return $false
    }

    try {
        $versionOutput = (& $Path '--version' 2>&1 | Out-String).Trim()
        return $versionOutput -match 'MySQL' -and $versionOutput -match '\b8\.'
    } catch {
        return $false
    }
}

function Get-MySqlServerCommand {
    param(
        [string] $ConfiguredPath = '',
        [string[]] $KnownPaths = @()
    )

    if (-not [string]::IsNullOrWhiteSpace($ConfiguredPath)) {
        if (-not (Test-Path $ConfiguredPath)) {
            throw "Configured MYSQLD_BIN was not found: $ConfiguredPath"
        }

        if (Test-MySqlServerBinaryCompatible -Path $ConfiguredPath) {
            return $ConfiguredPath
        }

        throw "Configured MYSQLD_BIN is not a MySQL Server 8 compatible mysqld.exe: $ConfiguredPath"
    }

    foreach ($knownPath in $KnownPaths) {
        if ((Test-Path $knownPath) -and (Test-MySqlServerBinaryCompatible -Path $knownPath)) {
            return $knownPath
        }
    }

    $command = Get-Command 'mysqld.exe' -ErrorAction SilentlyContinue
    if ($command) {
        if (Test-MySqlServerBinaryCompatible -Path $command.Source) {
            return $command.Source
        }

        throw "mysqld.exe was found at $($command.Source), but it is not MySQL Server 8 compatible. Start that database externally on DB_HOST:DB_PORT, install MySQL Server 8, or set MYSQLD_BIN to a MySQL Server 8 mysqld.exe."
    }

    return $null
}

function Initialize-LocalMySqlDataDir {
    param(
        [string] $MySqlServer,
        [string] $BaseDir,
        [string] $DataDir
    )

    if (Test-Path (Join-Path $DataDir 'mysql')) {
        return $false
    }

    New-Item -ItemType Directory -Force -Path $DataDir | Out-Null
    $existingEntries = @(Get-ChildItem -LiteralPath $DataDir -Force -ErrorAction SilentlyContinue)
    if ($existingEntries.Count -gt 0) {
        throw "Local MySQL data directory exists but is not a complete MySQL Server 8 data directory: $DataDir. Move or remove that generated runtime directory before retrying repo-local MySQL initialization."
    }

    & $MySqlServer '--initialize-insecure' "--basedir=$BaseDir" "--datadir=$DataDir" '--console'
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to initialize the repo-local MySQL data directory at $DataDir."
    }

    return $true
}

function Invoke-MySqlQuery {
    param(
        [string] $MySqlClient,
        [string] $User,
        [string] $Password,
        [int] $TargetPort,
        [string] $Query,
        [string] $Database = 'mysql'
    )

    $arguments = @('--protocol=tcp', '-h', '127.0.0.1', '-P', "$TargetPort", '-u', $User, '-D', $Database, '-N', '-B', '-e', $Query)
    $previousMySqlPassword = [Environment]::GetEnvironmentVariable('MYSQL_PWD')

    try {
        if (-not [string]::IsNullOrEmpty($Password)) {
            [Environment]::SetEnvironmentVariable('MYSQL_PWD', $Password)
        } else {
            [Environment]::SetEnvironmentVariable('MYSQL_PWD', $null)
        }

        & $MySqlClient @arguments 2>$null | Out-Null
        return $LASTEXITCODE -eq 0
    } finally {
        [Environment]::SetEnvironmentVariable('MYSQL_PWD', $previousMySqlPassword)
    }
}

function Ensure-MySqlCredentials {
    param(
        [string] $MySqlClient,
        [string] $DbUser,
        [string] $DbPassword,
        [string] $DatabaseName,
        [int] $TargetPort
    )

    if (-not $MySqlClient) {
        return $false
    }

    if (Invoke-MySqlQuery -MySqlClient $MySqlClient -User $DbUser -Password $DbPassword -TargetPort $TargetPort -Query 'SELECT 1') {
        if (-not [string]::IsNullOrWhiteSpace($DatabaseName)) {
            $escapedDatabaseName = $DatabaseName.Replace('`', '``')
            [void] (Invoke-MySqlQuery -MySqlClient $MySqlClient -User $DbUser -Password $DbPassword -TargetPort $TargetPort -Query ('CREATE DATABASE IF NOT EXISTS `{0}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' -f $escapedDatabaseName))
        }

        return $true
    }

    if ($DbUser -ne 'root' -or [string]::IsNullOrWhiteSpace($DbPassword)) {
        return $false
    }

    if (-not (Invoke-MySqlQuery -MySqlClient $MySqlClient -User 'root' -Password '' -TargetPort $TargetPort -Query 'SELECT 1')) {
        return $false
    }

    $escapedPassword = $DbPassword.Replace('''', '''''')
    $escapedDatabaseName = $DatabaseName.Replace('`', '``')
    $setupQuery = @(
        "ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY '$escapedPassword'",
        "ALTER USER IF EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '$escapedPassword'",
        ('CREATE DATABASE IF NOT EXISTS `{0}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' -f $escapedDatabaseName)
    ) -join '; '

    if (-not (Invoke-MySqlQuery -MySqlClient $MySqlClient -User 'root' -Password '' -TargetPort $TargetPort -Query $setupQuery)) {
        throw "Local MySQL started, but root credentials from .env could not be applied for database $DatabaseName."
    }

    return $true
}

$envValues = Read-DotEnvFile -Path $envFilePath
$resolvedPort = if ($PSBoundParameters.ContainsKey('Port')) {
    $Port
} else {
    [int] (Get-ConfigValue -Values $envValues -Key 'DB_PORT' -Default '3306')
}
$dbUser = Get-ConfigValue -Values $envValues -Key 'DB_USERNAME' -Default 'root'
$dbPassword = Get-ConfigValue -Values $envValues -Key 'DB_PASSWORD' -Default ''
$databaseName = Get-ConfigValue -Values $envValues -Key 'DB_DATABASE' -Default 'restaurantdb'
$configuredMySqlServer = Get-ConfigValue -Values $envValues -Key 'MYSQLD_BIN' -Default ''
$configuredMySqlClient = Get-ConfigValue -Values $envValues -Key 'MYSQL_BIN' -Default ''
$mySqlClient = Get-MySqlCommand -ExecutableName 'mysql.exe' -ConfiguredPath $configuredMySqlClient -KnownPaths $knownMySqlClientPaths
$mySqlDataMarker = Normalize-PathMarker -Path $mysqlDataDir
$mySqlAliasDataDir = Join-Path $repoRootAlias 'storage\mysql-local\data'
$mySqlAliasLogPath = Join-Path $repoRootAlias 'storage\logs\mysql-local-runtime.err'
$mySqlAliasMarkers = @(
    $mySqlDataMarker,
    (Normalize-PathMarker -Path $mySqlAliasDataDir)
)

if ($Stop) {
    $stopped = Stop-LocalMySql -TargetPort $resolvedPort -CommandLineMarkers $mySqlAliasMarkers
    if ($stopped) {
        Write-Output "Local MySQL stopped on 127.0.0.1:$resolvedPort. Data dir: $mysqlDataDir"
    } else {
        Write-Output "No repo-local MySQL process was listening on 127.0.0.1:$resolvedPort."
    }

    return
}

$existingConnection = Get-ListeningConnection -TargetPort $resolvedPort
if ($existingConnection) {
    $ownerProcess = Get-ProcessInfo -ProcessId $existingConnection.OwningProcess
    if (-not (Ensure-MySqlCredentials -MySqlClient $mySqlClient -DbUser $dbUser -DbPassword $dbPassword -DatabaseName $databaseName -TargetPort $resolvedPort)) {
        $ownerLabel = if ($ownerProcess) { "$($ownerProcess.Name) (PID $($ownerProcess.ProcessId))" } else { "PID $($existingConnection.OwningProcess)" }
        throw "Port $resolvedPort is already listening via $ownerLabel, but it did not accept the configured MySQL credentials. Ensure the service is MySQL-compatible and MYSQL_BIN, DB_USERNAME, DB_PASSWORD, and DB_DATABASE match .env."
    }

    if (-not $ownerProcess -or $ownerProcess.Name -ne 'mysqld.exe') {
        if ($Restart) {
            throw "Port $resolvedPort is already in use by an externally-managed MySQL-compatible service. Stop that service manually before using -Restart with the repo-local MySQL runtime."
        }

        $ownerLabel = if ($ownerProcess) { "$($ownerProcess.Name) (PID $($ownerProcess.ProcessId))" } else { "PID $($existingConnection.OwningProcess)" }
        Write-Output "MySQL-compatible service is already running on 127.0.0.1:$resolvedPort via $ownerLabel. Reusing the active service."
        return
    }

    $ownerMarker = Normalize-CommandLine -CommandLine $ownerProcess.CommandLine
    if ($ownerMarker -eq '') {
        Write-Output "MySQL is already running on 127.0.0.1:$resolvedPort with PID $($ownerProcess.ProcessId), but the process command line could not be inspected. Reusing the active mysqld instance."
        return
    }

    if (@($mySqlAliasMarkers | Where-Object { $ownerMarker.Contains($_) }).Count -eq 0) {
        Write-Output "MySQL is already running on 127.0.0.1:$resolvedPort with PID $($ownerProcess.ProcessId). Reusing the active mysqld instance."
        return
    }

    if (-not $Restart) {
        Write-Output "Local MySQL is already running on 127.0.0.1:$resolvedPort with PID $($ownerProcess.ProcessId). Data dir: $mysqlDataDir"
        return
    }
}

$mySqlServer = Get-MySqlServerCommand -ConfiguredPath $configuredMySqlServer -KnownPaths @($knownMySqlServer)
if (-not $mySqlServer) {
    throw 'mysqld.exe was not found. Install MySQL Server 8, add mysqld.exe to PATH, set MYSQLD_BIN in .env, or start a MySQL-compatible service on the configured DB_PORT before running this script.'
}

$mySqlBaseDir = Split-Path (Split-Path $mySqlServer -Parent) -Parent

New-Item -ItemType Directory -Force -Path $mysqlDataDir | Out-Null
New-Item -ItemType Directory -Force -Path $mysqlLogsDir | Out-Null
Ensure-Junction -LinkPath $repoRootAlias -TargetPath $repoRoot
Ensure-Junction -LinkPath $mySqlBaseAlias -TargetPath $mySqlBaseDir

if ($Restart) {
    [void] (Stop-LocalMySql -TargetPort $resolvedPort -CommandLineMarkers $mySqlAliasMarkers)
} elseif (@(Get-LocalMySqlProcesses -CommandLineMarkers $mySqlAliasMarkers).Count -gt 0) {
    [void] (Stop-LocalMySql -TargetPort $resolvedPort -CommandLineMarkers $mySqlAliasMarkers)
}

$initializedDataDir = Initialize-LocalMySqlDataDir -MySqlServer $mySqlServer -BaseDir $mySqlBaseDir -DataDir $mysqlDataDir

[void] (Start-Process `
    -FilePath $mySqlServer `
    -ArgumentList @(
        '--console',
        "--basedir=$($mySqlBaseAlias.Replace('\', '/'))",
        "--datadir=$($mySqlAliasDataDir.Replace('\', '/'))",
        "--port=$resolvedPort",
        '--bind-address=127.0.0.1',
        '--mysqlx=0',
        "--log-error=$($mySqlAliasLogPath.Replace('\', '/'))"
    ) `
    -WorkingDirectory $repoRootAlias `
    -WindowStyle Minimized `
    -PassThru)

if (-not (Wait-ForPortState -TargetPort $resolvedPort -ExpectListening $true -Attempts 40 -DelayMs 500)) {
    throw "Local MySQL did not start listening on 127.0.0.1:$resolvedPort. Inspect $mysqlRuntimeLog for details."
}

if (-not (Ensure-MySqlCredentials -MySqlClient $mySqlClient -DbUser $dbUser -DbPassword $dbPassword -DatabaseName $databaseName -TargetPort $resolvedPort)) {
    throw "Local MySQL started, but it did not accept the configured credentials. Ensure MYSQL_BIN, DB_USERNAME, DB_PASSWORD, and DB_DATABASE match .env."
}

$activeConnection = Get-ListeningConnection -TargetPort $resolvedPort
$activeOwner = if ($activeConnection) { Get-ProcessInfo -ProcessId $activeConnection.OwningProcess } else { $null }
$statusPrefix = if ($initializedDataDir) { 'Local MySQL initialized and started' } else { 'Local MySQL started' }

Write-Output "$statusPrefix on 127.0.0.1:$resolvedPort with PID $($activeOwner.ProcessId). Data dir: $mysqlDataDir"
