# check-staging-credentials.ps1
# Sanity check staging environment variables and credentials without exposing secret values.

param (
    [switch]$Json,
    [switch]$Strict,
    [switch]$LocalOnly
)

$envFile = Join-Path $PSScriptRoot "..\..\.env"
if (Test-Path $envFile) {
    $envFile = (Resolve-Path $envFile).Path
} else {
    $envFile = $null
}

# Parse .env if it exists
$EnvVars = @{}
if ($envFile -and (Test-Path $envFile)) {
    Get-Content $envFile | ForEach-Object {
        $line = $_.Trim()
        if ($line.StartsWith("#") -or [string]::IsNullOrWhiteSpace($line)) {
            return
        }
        if ($line -match "^([^=]+)=(.*)$") {
            $key = $Matches[1].Trim()
            $val = $Matches[2].Trim()
            # Strip outer quotes
            if (($val.StartsWith('"') -and $val.EndsWith('"')) -or ($val.StartsWith("'") -and $val.EndsWith("'"))) {
                $val = $val.Substring(1, $val.Length - 2)
            }
            $EnvVars[$key] = $val
        }
    }
}

# Helper to read environment variables prioritizing system env over .env
function Get-Val($varName) {
    $sysVal = [System.Environment]::GetEnvironmentVariable($varName)
    if ($sysVal) {
        return $sysVal
    } elseif ($EnvVars.ContainsKey($varName)) {
        return $EnvVars[$varName]
    } else {
        return $null
    }
}

# Define checked variables
$CoreVars = @(
    "APP_ENV",
    "APP_URL",
    "DB_HOST",
    "DB_DATABASE",
    "DB_USERNAME",
    "DB_PASSWORD",
    "REDIS_HOST"
)

$ExternalVars = @(
    "BACKUP_S3_BUCKET",
    "BACKUP_S3_PREFIX",
    "AWS_REGION",
    "AWS_ACCESS_KEY_ID",
    "MAIL_MAILER",
    "MAIL_HOST",
    "MAIL_USERNAME",
    "MAIL_PASSWORD",
    "MAIL_FROM_ADDRESS",
    "NOTIFICATION_SMOKE_EMAIL",
    "SENTRY_LARAVEL_DSN",
    "SENTRY_ENVIRONMENT",
    "OPS_ALERTS_WEBHOOK_URL",
    "VNPAY_TMN_CODE",
    "VNPAY_HASH_SECRET",
    "VNPAY_IPN_URL",
    "MOMO_PARTNER_CODE",
    "MOMO_ACCESS_KEY",
    "MOMO_SECRET_KEY",
    "MOMO_IPN_URL",
    "STAGING_BASE_URL"
)

$PlaceholderRegex = "^(changeme|example|placeholder|your-.*|null|local-simulated-.*|sandbox-.*-secret.*)$"

function Check-Status($val) {
    if ([string]::IsNullOrEmpty($val)) {
        return "MISSING"
    } elseif ($val -match $PlaceholderRegex) {
        return "PLACEHOLDER"
    } else {
        return "SET"
    }
}

$CoreMissing = $false
$ExternalMissing = $false
$VarStatus = @{}

foreach ($var in ($CoreVars + $ExternalVars)) {
    $val = Get-Val $var
    $status = Check-Status $val
    $VarStatus[$var] = $status
    if ($status -ne "SET") {
        if ($CoreVars -contains $var) {
            $CoreMissing = $true
        }
        if ($ExternalVars -contains $var) {
            $ExternalMissing = $true
        }
    }
}

# Calculate final status OK
$Ok = $true
if ($CoreMissing) {
    $Ok = $false
} elseif ($ExternalMissing -and $Strict -and -not $LocalOnly) {
    $Ok = $false
}

if ($Json) {
    # Format cleanly in JSON
    $jsonObj = [ordered]@{
        "ok" = $Ok
        "strict" = [bool]$Strict
        "local_only" = [bool]$LocalOnly
        "summary" = @{
            "core_runtime_ok" = [bool](-not $CoreMissing)
            "external_saas_ok" = [bool](-not $ExternalMissing)
        }
        "variables" = @{}
    }
    
    foreach ($var in ($CoreVars + $ExternalVars)) {
        $classification = if ($CoreVars -contains $var) { "core" } else { "external" }
        $jsonObj.variables[$var] = @{
            "status" = $VarStatus[$var]
            "classification" = $classification
        }
    }
    
    Write-Output ($jsonObj | ConvertTo-Json -Depth 4)
} else {
    Write-Output "Staging Credentials Sanity Check:"
    Write-Output "----------------------------------------"
    Write-Output "[CORE RUNTIME VARIABLES]"
    foreach ($var in $CoreVars) {
        Write-Output ("  {0,-30} : {1}" -f $var, $VarStatus[$var])
    }
    Write-Output ""
    Write-Output "[EXTERNAL SAAS VARIABLES]"
    foreach ($var in $ExternalVars) {
        Write-Output ("  {0,-30} : {1}" -f $var, $VarStatus[$var])
    }
    Write-Output "----------------------------------------"
    
    if ($CoreMissing) {
        Write-Error "[ERROR] Scoped Core Runtime credentials are MISSING or PLACEHOLDER."
        Write-Error "Cannot start basic runtime. Resolve DB/Redis configuration."
        exit 1
    } elseif ($ExternalMissing) {
        if ($Strict -and -not $LocalOnly) {
            Write-Error "[ERROR] STRICT MODE: Staging SaaS credentials are MISSING/PLACEHOLDER."
            exit 2
        } else {
            Write-Warning "[WARNING] Optional staging SaaS credentials are MISSING/PLACEHOLDER."
            Write-Warning "Staging environment will run in local-rehearsal/simulated mode."
        }
    } else {
        Write-Output "[SUCCESS] Staging credentials are fully populated!"
    }
}

if ($CoreMissing) {
    exit 1
}
if ($ExternalMissing -and $Strict -and -not $LocalOnly) {
    exit 2
}
exit 0
