# Verification — 14 September 2026

## Historical saved-revision baseline

The following evidence belongs to the previously saved verified revision. It is retained for traceability and is **not** treated as a fresh rerun after later continuation edits.

| Check                          | Recorded result                                                                                                                                                                                  |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| PHP runtime/dependencies       | PHP 8.4.1; Laravel 13.31.0 and Composer dependencies installed in that earlier workspace                                                                                                         |
| Node workspace                 | Node 24.19.0/npm 11.9; locked frontend dependencies installed                                                                                                                                    |
| Functional/unit suite          | 73 tests passed, 233 assertions, SQLite in-memory                                                                                                                                                |
| TypeScript                     | `npm run types:check` passed                                                                                                                                                                     |
| Frontend formatting/lint       | `npm run check` passed; 84 files formatted, 70 files linted                                                                                                                                      |
| Production build               | `npm run build` passed; 2,315 modules transformed                                                                                                                                                |
| PHP formatting/static analysis | Pint applied; PHPStan level 7 passed with zero errors and no suppression baseline; `composer test` passed                                                                                        |
| PDFs                           | Invoice, order, customer history and three-page measurements generated; invoice/measurement pages visually inspected; private customer-note sentinel absent from extracted text of all four PDFs |
| Queued CSV/XLSX                | Functional coverage recorded for queued generation, private download and spreadsheet formula-injection escaping                                                                                  |
| Browser end-to-end             | Test source existed but did not complete because browser downloads timed out/truncated                                                                                                           |
| PostgreSQL                     | Configuration/migrations present; PostgreSQL-specific constraints/concurrency not executed                                                                                                       |
| Docker/Windows                 | Setup files present; not executed on real Docker/Windows hosts                                                                                                                                   |
| Performance/accessibility      | Required large-volume benchmark and complete accessibility/device acceptance were not completed                                                                                                  |

## Current continuation source changes

This continuation keeps the earlier form-hardening work and adds a second source reconciliation pass:

- expanded report filtering across customer, product/service, workflow stage, billing state, invoice balance state, order date, invoice date, payment date, promised delivery, actual delivery and historical cutoff where applicable;
- URL-preserved report controls and browser test source for the named filters;
- owner-only internal report PDF route/view reuse with filter summaries and currency/timezone formatting;
- bulk customer-report statistics and bulk credit/refund/cost lookups to remove known row-by-row report query patterns;
- explicit dashboard/export refresh loading states and duplicate-refresh suppression;
- browser test source for native Back/Forward dirty-form confirmation in addition to modal/Inertia cases;
- guarded performance-fixture and benchmark scripts, plus a dedicated Docker `stitching_benchmark` database on fresh volumes;
- five additional PHPUnit test methods covering report dimensions/status/date behavior, customer-report filtering and owner-internal report PDFs. The PHP source now contains 78 test methods total, but the new suite has **not** been executed in this host.

## Checks actually executed in the current workspace

| Check                                                    | Current result                                                                                                                                                                                    |
| -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Environment                                              | PHP 8.4.23; Node 22.16.0; npm 10.9.2; Chromium 144.0.7559.96 at `/usr/bin/chromium`; Composer, Docker and `psql` unavailable                                                                      |
| PHP syntax                                               | **Passed:** `php -l` over 91 PHP files under `app`, `bootstrap`, `config`, `database`, `routes`, `tests` and `scripts/qa`; 0 failures                                                             |
| Studio/browser TS/TSX syntax smoke check                 | **Passed:** 9 TS/TSX files parsed with the locally available global TypeScript compiler; 0 syntax diagnostics. This is not the project `tsc --noEmit` check.                                      |
| Static button audit                                      | **Passed:** 0 native Studio `<button>` elements without an explicit `type`                                                                                                                        |
| Studio CSS structural smoke check                        | **Passed:** brace balance 0                                                                                                                                                                       |
| Performance QA scripts                                   | **Passed syntax only:** `scripts/qa/seed-performance.php` and `scripts/qa/benchmark-reports.php` both pass `php -l`; they were not executed because Laravel/vendor and PostgreSQL are unavailable |
| `composer test`                                          | **Blocked:** exit 127, `composer: command not found`; `vendor/` is absent                                                                                                                         |
| `php artisan --version` / Laravel-backed tests           | **Blocked:** Laravel bootstrap fails because `vendor/autoload.php` is absent                                                                                                                      |
| `npm ci --offline --ignore-scripts --no-audit --no-fund` | **Blocked:** exit 1. Current Node 22.16.0 is below multiple locked packages requiring `^22.18.0                                                                                                   |     | >=24.11.0`; cache also lacks `yuku-parser-0.5.48.tgz` (`ENOTCACHED`). Failed install left no `node_modules`. |
| `npm run build`                                          | **Blocked:** exit 127 because locked `vp`/Vite Plus binary is unavailable without dependency installation                                                                                         |
| Project `npm run types:check` / `npm run check`          | **Not claimable:** dependency installation is incomplete, so React/Inertia/Vite module declarations and project binaries are unavailable                                                          |
| Browser acceptance                                       | **Not executed:** Chromium itself is available, but the Playwright Node dependency/application dependency set is unavailable and Laravel cannot boot without Composer/vendor                      |
| PostgreSQL concurrency                                   | **Not executed:** no PostgreSQL client/service in this host                                                                                                                                       |
| 10k/30k/100k benchmark                                   | **Harness prepared, not executed:** no dependency-complete Laravel/PostgreSQL runtime                                                                                                             |
| Docker/Windows/backup/TLS                                | **Not executed:** target infrastructure unavailable here                                                                                                                                          |

Network dependency recovery is also unavailable in this environment; the missing Composer/npm artifacts cannot be safely fetched. No compile/test/build/browser/PostgreSQL/performance result is inferred from source presence alone.
