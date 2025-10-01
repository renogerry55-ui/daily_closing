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
set "PASSARG="
if defined DB_PASS set "PASSARG=-p%DB_PASS%"

for /f "delims=" %%F in ('dir /b /a:-d /o:n "*.up.sql"') do (
  echo Applying: %%F
  "%MYSQL_BIN%\mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% %PASSARG% %DB_NAME% < "%%F"
  if errorlevel 1 (
    echo [ERROR] Failed on %%F
    popd
    exit /b 1
  )
)
echo [OK] All migrations applied.
popd
