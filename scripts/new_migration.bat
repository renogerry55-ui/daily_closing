@echo off
setlocal EnableExtensions EnableDelayedExpansion

if "%~1"=="" (
  echo Usage: new_migration.bat descriptive_name
  echo Example: new_migration.bat add_hq_packages
  exit /b 1
)
set "NAME=%~1"

REM Use PowerShell for a robust timestamp regardless of locale
for /f %%t in ('powershell -NoProfile -Command "(Get-Date).ToString(\"yyyy-MM-dd_HHmm\")"') do set "TS=%%t"

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "MIG_DIR=%PROJECT_ROOT%\db\migrations"

if not exist "%MIG_DIR%" mkdir "%MIG_DIR%"

set "UP=%MIG_DIR%\%TS%_%NAME%.up.sql"
set "DOWN=%MIG_DIR%\%TS%_%NAME%.down.sql"

>"%UP%" echo -- %TS% %NAME% (UP)
>>"%UP%" echo -- Write your forward migration SQL below.
>>"%UP%" echo -- Example:
>>"%UP%" echo -- CREATE TABLE IF NOT EXISTS example (
>>"%UP%" echo --   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
>>"%UP%" echo --   name VARCHAR(100) NOT NULL,
>>"%UP%" echo --   PRIMARY KEY (id)
>>"%UP%" echo -- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

>"%DOWN%" echo -- %TS% %NAME% (DOWN)
>>"%DOWN%" echo -- Write your rollback SQL below.
>>"%DOWN%" echo -- Example:
>>"%DOWN%" echo -- DROP TABLE IF EXISTS example;

echo Created:
echo   %UP%
echo   %DOWN%
