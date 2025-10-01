# Daily Closing

## Database updates

Database patch files are stored in `database/updates`. Account operators must run the SQL in each numbered file (e.g. `001_create_hq_package_tables.sql`) against the production database before deploying related application features.

1. Review the assumptions block at the top of each script to confirm compatibility with your environment.
2. Apply the SQL in sequence, starting with the lowest file number.
3. Proceed with the application deployment only after all required patches have been executed successfully.

Keeping the database schema in sync ensures the HQ package workflow (including package totals and item assignments) functions correctly in the UI.
