@echo off
setlocal enabledelayedexpansion

set ROOT_DIR=%~dp0\..\..
for %%I in ("%ROOT_DIR%") do set ROOT_DIR=%%~fI
set SCHEMA_SQL=%ROOT_DIR%\database\schema\mysql-schema.sql
set PATCH_DIR=%ROOT_DIR%\database\patches
set VERIFY_SQL=%ROOT_DIR%\tools\mysql\verify_release_contract.sql

if "%DB_HOST%"=="" if not "%MYSQL_HOST%"=="" set DB_HOST=%MYSQL_HOST%
if "%DB_PORT%"=="" if not "%MYSQL_PORT%"=="" set DB_PORT=%MYSQL_PORT%
if "%DB_USERNAME%"=="" if not "%MYSQL_USER%"=="" set DB_USERNAME=%MYSQL_USER%
if "%DB_PASSWORD%"=="" if not "%MYSQL_PASSWORD%"=="" set DB_PASSWORD=%MYSQL_PASSWORD%
if "%DB_DATABASE%"=="" if not "%MYSQL_DATABASE%"=="" set DB_DATABASE=%MYSQL_DATABASE%

if "%DB_HOST%"=="" set DB_HOST=127.0.0.1
if "%DB_PORT%"=="" set DB_PORT=3306
if "%DB_USERNAME%"=="" set DB_USERNAME=root

if "%DB_DATABASE%"=="" (
  echo DB_DATABASE (or MYSQL_DATABASE) is required.
  exit /b 1
)

set MYSQL_BASE=mysql -h %DB_HOST% -P %DB_PORT% -u %DB_USERNAME% --default-character-set=utf8mb4
if not "%DB_PASSWORD%"=="" set MYSQL_BASE=%MYSQL_BASE% -p%DB_PASSWORD%

%MYSQL_BASE% -e "CREATE DATABASE IF NOT EXISTS `%DB_DATABASE%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 exit /b %errorlevel%

%MYSQL_BASE% %DB_DATABASE% < "%SCHEMA_SQL%"
if errorlevel 1 exit /b %errorlevel%

for %%F in ("%PATCH_DIR%\*.sql") do (
  %MYSQL_BASE% %DB_DATABASE% < "%%~fF"
  if errorlevel 1 exit /b %errorlevel%
)

%MYSQL_BASE% %DB_DATABASE% < "%VERIFY_SQL%"
if errorlevel 1 exit /b %errorlevel%

echo Release database bootstrap completed for %DB_DATABASE% with contract verification via %VERIFY_SQL%.
