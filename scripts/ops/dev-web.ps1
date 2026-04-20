param(
    [ValidateSet('customer-web', 'staff-web')]
    [string] $App,
    [string] $HostName = '127.0.0.1',
    [int] $Port
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $false

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$normalizedRepoRoot = ([System.IO.Path]::GetFullPath($repoRoot)).Replace('\', '/').ToLowerInvariant()

function Get-AppDefinition {
    param(
        [string] $Name,
        [int] $RequestedPort
    )

    switch ($Name) {
        'customer-web' {
            $resolvedPort = if ($RequestedPort -gt 0) { $RequestedPort } else { 3000 }
            return @{
                Label = 'customer-web'
                WorkingDirectory = Join-Path $repoRoot 'customer-web'
                Port = $resolvedPort
                HealthUrl = "http://$HostName`:$resolvedPort/login"
                StartupCommand = @('run', 'dev', '--', '--hostname', $HostName, '--port', "$resolvedPort")
                CommandMarkers = @(
                    '/customer-web/',
                    '/next/'
                )
            }
        }
        'staff-web' {
            $resolvedPort = if ($RequestedPort -gt 0) { $RequestedPort } else { 5173 }
            return @{
                Label = 'staff-web'
                WorkingDirectory = Join-Path $repoRoot 'staff-web'
                Port = $resolvedPort
                HealthUrl = "http://$HostName`:$resolvedPort/"
                StartupCommand = @('run', 'dev', '--', '--host', $HostName, '--port', "$resolvedPort")
                CommandMarkers = @(
                    '/staff-web/',
                    '/vite'
                )
            }
        }
    }

    throw "Unsupported app [$Name]."
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

function Normalize-CommandLine {
    param(
        [string] $Value
    )

    if ([string]::IsNullOrWhiteSpace($Value)) {
        return ''
    }

    return ([string] $Value).Replace('\', '/').ToLowerInvariant()
}

function Test-RepoAppProcess {
    param(
        $ProcessInfo,
        [hashtable] $Definition
    )

    if (-not $ProcessInfo -or $ProcessInfo.Name -notin @('node.exe', 'node')) {
        return $false
    }

    $normalizedCommandLine = Normalize-CommandLine -Value $ProcessInfo.CommandLine
    if ($normalizedCommandLine -eq '' -or -not $normalizedCommandLine.Contains($normalizedRepoRoot)) {
        return $false
    }

    foreach ($marker in $Definition.CommandMarkers) {
        if (-not $normalizedCommandLine.Contains($marker)) {
            return $false
        }
    }

    return $true
}

function Test-AppEndpoint {
    param(
        [string] $Url,
        [int] $TimeoutSec = 5
    )

    try {
        $response = Invoke-WebRequest -Uri $Url -Method Get -UseBasicParsing -TimeoutSec $TimeoutSec
        return $response.StatusCode -ge 200 -and $response.StatusCode -lt 400
    } catch {
        return $false
    }
}

$definition = Get-AppDefinition -Name $App -RequestedPort $Port
$npmCommand = Get-Command npm.cmd -ErrorAction SilentlyContinue
if (-not $npmCommand) {
    $npmCommand = Get-Command npm -ErrorAction SilentlyContinue
}

if (-not $npmCommand) {
    throw 'npm was not found. Install Node.js/npm before using the local dev web wrapper.'
}

$existingConnection = Get-ListeningConnection -TargetPort $definition.Port
if ($existingConnection) {
    $ownerProcess = Get-ProcessInfo -ProcessId $existingConnection.OwningProcess

    if (-not (Test-RepoAppProcess -ProcessInfo $ownerProcess -Definition $definition)) {
        $ownerLabel = if ($ownerProcess) { "$($ownerProcess.Name) (PID $($ownerProcess.ProcessId))" } else { "PID $($existingConnection.OwningProcess)" }
        throw "Port $($definition.Port) is already in use by $ownerLabel. Stop that process or switch the $($definition.Label) dev port before running npm run dev:all."
    }

    if (-not (Test-AppEndpoint -Url $definition.HealthUrl -TimeoutSec 5)) {
        Write-Host "$($definition.Label) on port $($definition.Port) is stale. Restarting the repo-local process..."
        Stop-Process -Id $ownerProcess.ProcessId -Force -ErrorAction SilentlyContinue

        if (-not (Wait-ForPortState -TargetPort $definition.Port -ExpectListening $false -Attempts 30 -DelayMs 500)) {
            throw "$($definition.Label) did not release port $($definition.Port) after the restart signal."
        }
    } else {
        Write-Host "Reusing $($definition.Label) on http://$HostName`:$($definition.Port) (PID $($ownerProcess.ProcessId))."
        Wait-Process -Id $ownerProcess.ProcessId
        exit 0
    }
}

Set-Location $definition.WorkingDirectory
Write-Host "Starting $($definition.Label) on http://$HostName`:$($definition.Port)"
& $npmCommand.Source @($definition.StartupCommand)
exit $LASTEXITCODE
