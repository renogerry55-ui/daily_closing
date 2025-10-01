# Database Migrations (Windows + XAMPP)

This adds a simple migration workflow for a PHP + MySQL project on Windows.

## Folder layout
```
/db
  /migrations         # put *.up.sql and *.down.sql here
  /seed               # optional dev seed data
  /dumps              # optional: full schema exports (ignored by git)
/scripts               # helper scripts (.bat)
.env.example           # copy to .env and fill values
.gitignore
README_DB.md
```

## Setup
1. Copy `.env.example` → `.env` and fill DB creds.
   - Confirm MySQL path: `C:\xampp\mysql\bin\mysql.exe -V`
   - If your MySQL is elsewhere, edit `MYSQL_BIN` in `.env`.
2. Ensure your project root looks like:
   ```
   daily_closing/
     db/
     scripts/
     .env
   ```

## Creating a migration
```
scripts\new_migration.bat add_something
```
This creates two files in `db\migrations` with a timestamped name:
- `YYYY-MM-DD_HHMM_add_something.up.sql`  (forward)
- `YYYY-MM-DD_HHMM_add_something.down.sql` (rollback)

Paste SQL from Codex:
- **UP** → `.up.sql`
- **DOWN** → `.down.sql`

## Applying migrations
- Latest only: `scripts\apply_latest.bat`
- From scratch: `scripts\apply_all.bat`
- Rollback latest: `scripts\rollback_latest.bat`

## Conventions
- Engine/charset: InnoDB / utf8mb4_unicode_ci
- Unsigned IDs where appropriate; add meaningful indexes
- Foreign keys: `ON DELETE RESTRICT` unless stated otherwise
- Keep migrations idempotent when practical (guards, IF NOT EXISTS)
