@echo off
setlocal enabledelayedexpansion

set ROOT_DIR=%~dp0\..\..
pushd "%ROOT_DIR%" >nul

if exist scripts\ci\booking-repo-prereq-check.sh (
  bash scripts/ci/booking-repo-prereq-check.sh --profile=package-release
  if errorlevel 1 goto :fail
)

php artisan booking:release-build %*
if errorlevel 1 goto :fail

popd >nul
exit /b 0

:fail
popd >nul
exit /b 1
