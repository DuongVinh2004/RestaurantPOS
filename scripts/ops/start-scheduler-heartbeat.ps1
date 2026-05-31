param(
    [Parameter(Mandatory=$false)]
    [switch]$Once,

    [Parameter(Mandatory=$false)]
    [switch]$Watch,

    [Parameter(Mandatory=$false)]
    [switch]$SupervisorSample,

    [Parameter(Mandatory=$false)]
    [switch]$Help
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$artisanPath = Join-Path $repoRoot 'artisan'

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

function Show-HelpMessage {
    Write-Output "Staging/Local Scheduler Heartbeat Operator Lane"
    Write-Output "Usage: .\start-scheduler-heartbeat.ps1 [-Once] [-Watch] [-SupervisorSample]"
    Write-Output ""
    Write-Output "Options:"
    Write-Output "  -Once              Touch the scheduler heartbeat once and exit (for validation)."
    Write-Output "  -Watch             Run a foreground loop to keep touching the heartbeat every 60 seconds."
    Write-Output "  -SupervisorSample  Print the sample Supervisor configuration for staging and exit."
    Write-Output "  -Help              Show this help message."
}

function Show-SupervisorSample {
    Write-Output "================================================================================"
    Write-Output "Sample Supervisor Configuration for Staging Scheduler / Queue Workers"
    Write-Output "================================================================================"
    Write-Output "Place this configuration in /etc/supervisor/conf.d/restaurantpos-scheduler.conf:"
    Write-Output ""
    Write-Output "[program:restaurantpos-scheduler]"
    Write-Output "process_name=%(program_name)s"
    Write-Output "command=php /var/www/restaurantpos/artisan schedule:work"
    Write-Output "user=www-data"
    Write-Output "autostart=true"
    Write-Output "autorestart=true"
    Write-Output "stopasgroup=true"
    Write-Output "killasgroup=true"
    Write-Output "redirect_stderr=true"
    Write-Output "stdout_logfile=/var/www/restaurantpos/storage/logs/scheduler-daemon.log"
    Write-Output ""
    Write-Output "To apply:"
    Write-Output "  sudo supervisorctl reread"
    Write-Output "  sudo supervisorctl update"
    Write-Output "  sudo supervisorctl start restaurantpos-scheduler"
    Write-Output "================================================================================"
}

function Assert-Environment {
    if (-not (Test-Path $artisanPath)) {
        throw "artisan was not found in repo root: $repoRoot"
    }
}

function Run-Once {
    Assert-Environment
    $php = Resolve-PhpExecutable
    Write-Output "Touching scheduler heartbeat once..."
    & $php $artisanPath 'booking:ops-heartbeat:touch' 'scheduler' '--json'
    Write-Output "Heartbeat touched successfully."
}

function Run-Watch {
    Assert-Environment
    $php = Resolve-PhpExecutable
    Write-Output "Starting scheduler heartbeat watch loop (every 60s)... Press Ctrl+C to stop."
    
    try {
        while ($true) {
            & $php $artisanPath 'booking:ops-heartbeat:touch' 'scheduler' '--json'
            Start-Sleep -Seconds 60
        }
    } catch [System.Management.Automation.PipelineStoppedException] {
        Write-Output "Watch loop stopped cleanly."
    } catch {
        Write-Error $_
    }
}

# Execution Entry Point
if ($Help) {
    Show-HelpMessage
    exit 0
}

if ($SupervisorSample) {
    Show-SupervisorSample
    exit 0
}

if ($Once) {
    Run-Once
    exit 0
}

if ($Watch) {
    Run-Watch
    exit 0
}

# If no switch provided, display help
Show-HelpMessage
exit 1
