# Operations and data rules

## Deployment

Use a PHP 8.4 FPM/web-server host with PostgreSQL and Node 24 for asset builds. Serve only `public/`. Set APP_ENV=production, APP_DEBUG=false, APP_URL to the HTTPS origin and SESSION_SECURE_COOKIE=true. Configure trusted proxies to match your actual reverse proxy. Keep storage and bootstrap/cache writable by the app user. Keep uploads and generated exports private; do not expose storage/app/private.

Install with `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`, then `php artisan migrate --force`, `php artisan db:seed --force`, and `php artisan optimize`. Provision the owner interactively. Configure mail delivery if password-reset email is wanted. Public signup stays disabled.

Run a supervised `php artisan queue:work --tries=3 --timeout=300`; configure queue retry_after longer than the worker timeout (for example 360 seconds). Run `php artisan schedule:run` every minute as the application user. Restart workers after deployment with `php artisan queue:restart`. Monitor failed_jobs and application logs; failed exports can be retried from the exports screen. Development `artisan serve` is not the production web server.

## Backup and restore

Back up PostgreSQL with pg_dump custom format using securely supplied database credentials. Back up private uploaded logos and any required export files, plus the APP_KEY and deployment configuration in an encrypted secrets store. A database-only backup does not contain the application key or uploaded files. Retain at least one off-host copy.

Test restoration into a separate empty database using pg_restore, restore matching private files and APP_KEY, and validate owner login, customer/order counts, invoice balances and sample PDFs before switching traffic. Never test a restore over live data. Before upgrades, take a backup, use maintenance mode for migrations that require it, deploy matching code/assets, run migration and smoke checks, restart workers, and leave maintenance mode. If rollback is needed, restore compatible code and database together rather than blindly rolling back ledger migrations.

## Money and historical integrity

Amounts are integer minor units, never floating-point ledger entries. Percentages use basis points. Decimal input uses bcmath with half-up rounding; header discounts distribute remainder deterministically across lines. Tax order matters for compound taxes. Invoice line/contact/tax values freeze at posting; measurement revisions retain field definitions and values.

Payments and invoice allocations are separate: an advance can remain unallocated; pending bank payments cannot settle an invoice until settled. Credits settle unpaid debt first, with the remainder available for capped refunds. A refund is separate from the credit. Financial effective dates cannot precede existing invoice events. Reversing allocations after a credit is currently blocked to protect the remaining refund amount.

## Relationships

Customer → orders → order lines → measurement snapshot revisions. Each order can have one invoice with frozen lines. Customer payments → allocations → invoices. Invoice credits → credit allocations and refunds. Business settings/templates/products configure new records; historical snapshots remain independent. Audit events record domain actions; report exports belong to their requesting user. See database/migrations for exact constraints and foreign keys.

## Retention

Do not delete financial history or measurement revisions to correct mistakes. Use available reversal/credit/revision workflows. Customer/product archival preserves linked history. Export files accumulate privately; remove expired exports only under an operator-defined policy and update the related records. No scheduled destructive cleanup is shipped.

## Isolated performance acceptance

The required scale fixture and report/export benchmark must never run against the business database. A fresh Docker PostgreSQL volume creates a separate `stitching_benchmark` database for this purpose. Both QA scripts independently refuse to run unless `APP_ENV=testing` and the configured database name/path contains `benchmark` or `performance`.

Example from the workspace container after dependencies are installed:

```bash
APP_ENV=testing DB_CONNECTION=pgsql DB_HOST=postgres DB_DATABASE=stitching_benchmark \
  php scripts/qa/seed-performance.php
APP_ENV=testing DB_CONNECTION=pgsql DB_HOST=postgres DB_DATABASE=stitching_benchmark \
  php scripts/qa/benchmark-reports.php
```

`seed-performance.php` resets only the guarded disposable database and creates exactly 10,000 orders, 30,000 order lines and 100,000 measurement values, plus representative invoices, payments, credits and refunds. `benchmark-reports.php` records row counts, query counts, database time, elapsed time, memory usage, environment details, and CSV/XLSX export timing. It writes a copy of the metrics to `/tmp/stitching-benchmark-results.json`; review the output and define/record acceptance thresholds before treating scale work as passed.
