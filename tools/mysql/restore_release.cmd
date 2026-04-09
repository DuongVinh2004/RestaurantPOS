@echo off
setlocal enabledelayedexpansion
set SCRIPT_DIR=%~dp0
set PHP_BIN=%PHP_BINARY_PATH%
if "%PHP_BIN%"=="" set PHP_BIN=php
"%PHP_BIN%" "%SCRIPT_DIR%restore_release.php" %*
exit /b %ERRORLEVEL%
