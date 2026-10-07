# Stitching Studio

Laravel 13 + React/TypeScript/Inertia application for a single-owner stitching business. Includes customers, configurable garments and measurement templates, frozen measurement revisions, order production stages, invoices, advances and partial payments, credits/refunds, reports, private PDF/CSV/XLSX downloads, follow-ups and an audit trail.

## Run on Windows

With Docker Desktop running, extract the source, open PowerShell in this folder:

```powershell
powershell -File scripts/workspace/start-windows.ps1
```

The script installs locked PHP/Node dependencies, initializes PostgreSQL, seeds templates, runs checks, asks for your owner email and a private password, then starts services. Open http://localhost:8000. There is no public registration or default owner password.

Read [installation](docs/WORKSPACE.md), [operations](docs/OPERATIONS.md), [verification](docs/VERIFICATION.md), and [scope and limitations](docs/REQUIREMENTS.md), and the [requirement-to-evidence acceptance checklist](docs/ACCEPTANCE-CHECKLIST.md). Reports include URL-preserved business filters, private CSV/XLSX exports and owner-only internal report PDFs. Guarded QA scripts can build the required 10,000-order / 30,000-line / 100,000-measurement-value benchmark fixture in a disposable benchmark database. This is an implemented application source release; PostgreSQL concurrency, browser acceptance, scale execution and production deployment still need validation. It is not a claim that every criterion in the original specification has passed.

First use: configure business details and payment methods, add product prices, add a customer, create an order, fill measurements and confirm it. Create and post the invoice; record payments as received. Advance through Cutting, Stitching, Ready and Delivered. Synthetic QA fixtures are never installed into your business database.
