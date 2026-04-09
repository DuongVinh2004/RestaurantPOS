@echo off
setlocal
set SCRIPT_DIR=%~dp0
php "%SCRIPT_DIR%backup_release.php" %*
endlocal
