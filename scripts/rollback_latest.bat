@echo off
setlocal EnableExtensions EnableDelayedExpansion

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "ENV_FILE=%PROJECT_ROOT%\.env"
set "MIG_DIR=%PROJECT_ROOT%\db\migrations"

if not exist "%ENV_FILE%" (
  echo [ERROR] .env not found at %ENV_FILE%
  exit /b 1
)
if not exist "%MIG_DIR%" (
  echo [ERROR] Migrations folder not found at %MIG_DIR%
  exit /b 1
)

for /f "usebackq eol=# tokens=1,2 delims==" %%A in ("%ENV_FILE%") do (
  set "%%A=%%B"
)

pushd "%MIG_DIR%"
set "LATEST="
for /f "delims=" %%F in ('dir /b /a:-d /o:-n "*.down.sql"') do (
  set "LATEST=%%F"
  goto :FOUND
)
echo [INFO] No *.down.sql rollback files found.
popd
exit /b 0

:FOUND
echo Rolling back: %LATEST%

set "PASSARG="
if defined DB_PASS set "PASSARG=-p%DB_PASS%"

"%MYSQL_BIN%\mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% %PASSARG% %DB_NAME% < "%LATEST%"
if errorlevel 1 (
  echo [ERROR] Failed on %LATEST%
  popd
  exit /b 1
)
echo [OK] Rolled back %LATEST%
popd
