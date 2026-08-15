<div align="center">
  <img src="public/assets/images/plexiq-icon.png" alt="PlexiQ LIMS" width="120">
  <h1>PlexiQ LIMS</h1>
  <p><strong>Advanced Laboratory Information Management System</strong></p>
  <p>PHP 8.0+ · PostgreSQL · Docker-Ready</p>
</div>

## Overview

PlexiQ LIMS is a self-hosted, enterprise-grade Laboratory Information Management System designed for QC/QA laboratories. It provides complete sample lifecycle management, instrument integration, multi-stage approvals, and regulatory compliance (21 CFR Part 11, GDPR, HIPAA).

## Features

**Sample & Batch Management**
- Full lifecycle tracking (login → testing → approval → COA)
- Multi-stage approval workflow (Analyst → Reviewer → Approver)
- Barcode/QR code generation and scanning
- Test result entry with specification limits and measurement uncertainty (value ± expanded uncertainty with coverage factor k and confidence interval)
- Chain of custody tracking (`/coc`) — sealed custody transfers, receipt acknowledgment, and a per-sample custody timeline

**Quality Management**
- OOS (Out-of-Specification) investigations
- CAPA (Corrective and Preventive Actions)
- Deviation management with action tracking
- Analysis parameter management (spec limits, per-sample assignment, Analyst -> Reviewer -> Approver result workflow)
- SPC control charts (X-bar, R, Sigma) with Nelson out-of-control rules (all 8 rules, violations highlighted on chart)
- QC control module (`/qc`) — control lots, Levey-Jennings charts with ±1/2/3 SD bands, Westgard multi-rules (1₃ₛ, 2₂ₛ, R₄ₛ, 4₁ₛ, 10ₓ)
- Stability studies with multi-timepoint tracking

**Regulatory Compliance**
- 21 CFR Part 11 electronic signatures & audit trail
- Two-factor authentication (TOTP) with per-user QR setup and recovery codes
- Soft deletes across all master records — historical data and audit trails are retained while records are hidden from active workflows
- GDPR data privacy & consent logging
- HIPAA compliance controls
- Data retention policies
- Comprehensive audit logging

**Integrations**
- SAP HANA (OData + ODBC, bidirectional sync)
- Instrument file import (CSV, XML, text parsers) with column-to-parameter mapping and auto-fetch from watch folders
- REST API with token authentication & webhooks
- SSO provider support
- Email notifications with per-user preference settings

**Additional Modules**
- Electronic Lab Notebook (ELN) with attachments
- Environmental monitoring with alerts
- Customer self-service portal
- Chemical inventory management
- Calibration scheduling & tracking
- Supplier qualification management
- Training management with course assignments
- BI analytics with custom report builder
- Billing & invoicing
- Multi-language i18n
- Plugin system

## Requirements

| Component | Requirement |
|-----------|-------------|
| PHP | 8.0 – 8.3 (PDO with `pdo_pgsql` enabled) |
| Database | PostgreSQL 12+ (recommended: 16, tested on 18) |
| Web Server | Apache with `mod_rewrite` (or Nginx, or PHP built-in server) |
| Extensions | `json`, `curl`, `session`, `mbstring`, `pdo_pgsql`, `pgsql` |
| Optional | `odbc` (SAP HANA), `dom` / `dompdf` (PDF COA) |

> **XAMPP users:** enable the PostgreSQL drivers before starting — in `C:\xampp\php\php.ini` uncomment:
> ```
> extension=pdo_pgsql
> extension=pgsql
> ```

## Scalability & Performance

PlexiQ ships with production-grade scalability features that are safe to enable as traffic grows:

| Feature | How to enable | Notes |
|---------|--------------|-------|
| **List pagination** | On by default | All large list pages (billing, COA, deviations, ELN, stability, notifications, calibrations, inventory, users, audit login history, environmental readings/alerts, compliance logs, barcode scan logs, training courses, SAP sync logs, translations) paginate with prev/next controls and a record count. |
| **Dashboard caching** | `CACHE_DRIVER=file` in `.env` | Dashboard stats and recent samples are cached for 60s; caches are invalidated automatically when samples change. `redis` is supported for multi-node setups. |
| **Database sessions** | `SESSION_DRIVER=database` | Sessions stored in the `sessions` table — required when running multiple web servers behind a load balancer (file sessions won't share). |
| **Async job queue** | `QUEUE_DRIVER=database` + run the worker | Webhook deliveries are enqueued and processed in the background instead of blocking requests. See "Queue Worker" below. |
| **Performance indexes** | Apply `database/migrations/012_scalability.sql` | Adds the `jobs` and `sessions` tables plus ~16 high-traffic query indexes. |

### Queue Worker

Webhooks (e.g. `sample.created`, sample status changes) are delivered asynchronously through the `jobs` table. Start the worker in production:

```bash
php bin/worker.php                          # run forever
php bin/worker.php --once                   # process a single job
php bin/worker.php --queue=webhooks --stop-when-empty
```

Monitor via the CLI console: `php bin/console queue:monitor` (also lists pending/failed counts). Failed jobs are retried up to 3 times with exponential backoff.

### Console Commands

```bash
php bin/console queue:work --queue=webhooks --once
php bin/console queue:monitor
php bin/console cache:clear
```

### Production Deployment

Reference configs live in `deploy/`:

- `deploy/nginx.conf` — Nginx virtual host (front controller, static asset caching, hardened headers)
- `deploy/php-fpm.conf` — PHP-FPM pool (static sizing, slow-log, hard limits)
- `.env.example` — production environment template (disable debug, generate a real `APP_KEY`)

### Client Portal

Customers log in at **/client/login** (register at /client/register). Customer-role accounts land on `/client/dashboard` automatically after sign-in; a "Customer Portal" link is shown on the staff login page. Seeded customers: `customer`, `customer1` (password `admin@123`).

## Quick Start

### Docker (Recommended)

```bash
docker compose up -d
```

Then visit `http://localhost:8080` and run the web installer.

### Manual (XAMPP / WAMP)

1. Clone the repo into your web root:
   ```bash
   git clone https://github.com/bhnvboy-cell/plexiq-lims.git
   cd plexiq-lims
   ```
2. Copy environment config and edit database credentials:
   ```bash
   copy .env.example .env
   ```
   Set `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` to match your PostgreSQL server.
3. Create the database (or `CREATE DATABASE limsdb;`) and load the schema + seed data:
   ```bash
   psql -U postgres -d limsdb -f database/schema.sql
   psql -U postgres -d limsdb -f database/seed_data.sql
   ```
4. Run migrations (in filename order):
   ```bash
   for %f in (database/migrations/*.sql) do psql -U postgres -d limsdb -f "%f"
   ```
   > **Note:** migration `011_notification_settings.sql` creates the `notification_settings` table required by the Notifications module, and `012_scalability.sql` adds the `jobs`/`sessions` tables and performance indexes (required by the queue worker and database session driver). `013_backup.sql` adds the `backup_settings`/`backup_runs` tables used by the Backup & Restore module, and `014_analysis_parameters.sql` adds the analysis parameter master, sample/batch assignments and instrument column mapping used by the Analysis Parameters module — apply all migrations, or the affected pages will fail. `015_foundation_security.sql` adds TOTP 2FA columns and audit/retention security rows, and `016_coa_template_columns.sql` adds COA template layout fields (page size, watermark, QR toggle).
5. Start the dev server (or double-click `serve.bat`):
   ```bash
   C:\xampp\php\php.exe -S 0.0.0.0:8080 -t public public/router.php
   ```
   The `public/router.php` router script is required in development so that download/restore URLs containing a `.sql` suffix route through the front controller (the built-in server treats extension URLs as static files otherwise). Use `0.0.0.0` instead of `localhost` to access the app from phones/tablets on the same network.
6. Open **http://localhost:8080** and log in.

### Default Login

| Username | Password |
|----------|----------|
| `admin` | `admin@123` |

Other seeded users: `analyst`, `reviewer`, `approver`, `customer` — all use password `admin@123`.

### Mobile / Tablet Access

1. Start the server bound to all interfaces: `php -S 0.0.0.0:8080 -t public`
2. Allow port 8080 through Windows Firewall (run as Administrator):
   ```bash
   New-NetFirewallRule -DisplayName "PlexiQ LIMS 8080" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow
   ```
3. On the device (same Wi-Fi), open `http://<PC-LAN-IP>:8080` (find it with `ipconfig`).

### Installers

Pre-built installers for Windows are available under `client-installer/` and `server-installer/`.

## Configuration

Edit `.env` to configure:

| Variable | Description |
|----------|-------------|
| `APP_NAME` | Application display name |
| `APP_ENV` | `production` or `development` |
| `DB_*` | PostgreSQL connection settings |
| `SAP_HANA_*` | SAP HANA integration settings |
| `MAIL_*` | Email / SMTP configuration |
| `COMPANY_*` | Company info for COA documents |

Generate an application key:
```bash
php bin/console key:generate
```

### Backup & Restore

Backups are plain SQL dumps (`pg_dump -Fp --clean --if-exists --no-owner`) stored in `storage/backups/`, each with a `.meta.json` sidecar (sha256 checksum, size, type, app version) used to detect tampering.

**Web UI** — Administrators: **Administration → Backup & Restore** (`/backups`). Create, download, restore (type `RESTORE` to confirm — this **overwrites the database**), delete, and set retention / binary paths.

**CLI:**

```bash
# Create a manual backup
php bin/console backup:run --type=manual

# List backups
php bin/console backup:list

# Restore (destructive!)
php bin/console backup:restore <file.sql>

# Prune old backups (apply retention)
php bin/console backup:prune
```

**Scheduled backups** — add a cron job (Linux) or Scheduled Task (Windows):

```bash
0 2 * * * cd /path/to/plexiq && /usr/bin/php bin/console backup:run --type=scheduled
```

**Configuration** (`.env` or the Backup Settings UI):

| Variable | Description |
|----------|-------------|
| `BACKUP_RETENTION` | Number of backups to keep (default `10`, min `1`) |
| `PG_DUMP_PATH` | Path to `pg_dump.exe` (auto-detected if empty) |
| `PSQL_PATH` | Path to `psql.exe` (auto-detected if empty) |

Restore replays the dump with `psql -v ON_ERROR_STOP=1`, so any SQL error aborts the restore. Every operation is written to the audit trail.

### Analysis Parameters & Instrument Auto-Fetch

Migration `014_analysis_parameters.sql` adds the parameter master (`analysis_parameters`), per-sample and per-batch assignments (`sample_analysis_parameters`, `batch_analysis_parameters`), and instrument column mapping (`instrument_parameter_mapping`).

**Parameter workflow** — Administrators define parameters with spec limits, data type and method. Analysts assign parameters to a sample (`/samples/{id}/parameters`), then record results. Results flow through **Completed -> Reviewed -> Approved**; approvals auto-feed the SPC module, and any out-of-spec result auto-creates an OOS record (OOS-xxx) with notifications to Reviewers/Approvers.

**Instrument mapping & auto-fetch** — On **Instruments -> Column Mapping** (`/instruments/{id}/mappings`) an admin maps a source column (header in the instrument file) to an analysis parameter with an optional conversion factor and unit. Uploading a file from the instrument's Import page enqueues an `ImportInstrumentFile` job (queue `imports`) instead of blocking the request; the worker parses the file, resolves each row to a sample by `sample_code`, converts the value, writes `sample_analysis_parameters` (spec validation + auto-OOS) and records the raw row in `instrument_results` with the `source_file` for dedupe/audit.

```bash
# Process queued instrument imports (add to cron / Scheduled Task)
php bin/worker.php --queue=imports --stop-when-empty

# Scan all auto-import instruments' watch folders and enqueue new files
php bin/console instrument:scan
```

`ImportInstrumentFile` and `WatchInstrumentDirectories` jobs run on the `imports` queue. Every import, assignment and workflow step is written to the audit trail.

## Beta Testing

The `beta/` directory contains demo-data seeding and automated HTTP test scripts used to validate every module end-to-end:

| Script | Purpose |
|--------|---------|
| `beta/seed_beta_data.php` | Idempotent seeder that inserts ~100 customers, 200 samples, 60 batches, ~440 test assignments/results (incl. OOS, uncertainty), deviations, CAPA, ELN, environmental, suppliers, training, SPC/QC/stability, calibrations, CoC, billing, projects, compliance, i18n and e-signature data across every module. Re-run safely — it cleans up its own rows first. |
| `beta/smoke_test.php` | Logs in as `admin` and walks 156 module pages (list/detail/create/edit incl. COA PDF, labels), reporting any non-200 response. |
| `beta/role_test.php` | Logs in as analyst/reviewer/approver/customer and verifies each role's page access and permission boundaries (35 checks). |
| `beta/workflow_test.php` | Runs a real cycle on a throwaway test: Analyst enters result → Reviewer approves → Approver final-approves, then cleans up. |

```bash
php beta/seed_beta_data.php   # load demo data (run from project root)
php beta/smoke_test.php       # full admin page walk (expect: ALL ADMIN PAGES OK)
php beta/role_test.php        # role/permission checks (expect: ALL ROLE CHECKS OK)
php beta/workflow_test.php    # end-to-end approval cycle
```

## Troubleshooting

| Symptom | Cause & Fix |
|---------|-------------|
| `'php' is not recognized` | PHP is not on the PATH. Use the full path (`C:\xampp\php\php.exe`) or add `C:\xampp\php` to your system PATH. |
| `could not find driver` | PostgreSQL drivers disabled. Uncomment `extension=pdo_pgsql` and `extension=pgsql` in `php.ini` and restart the server. |
| `database "limsdb" does not exist` | Create the database and load `database/schema.sql`, `database/seed_data.sql`, then the migrations. |
| `undefined table` / `undefined column` errors | Re-run all files under `database/migrations/` — several modules depend on them (e.g. `011_notification_settings.sql` for `/notifications/settings`). |
| `relation "notification_settings" does not exist` | Run `database/migrations/011_notification_settings.sql`. Default rows are auto-created for each user on first visit to `/notifications/settings`. |
| 500 on a specific module | Check `storage/logs/lims-error.log` — it logs the exact failing query with a stack trace. |

## Running Tests

```bash
# All tests
php phpunit.phar

# Unit tests only
php phpunit.phar --testsuite Unit

# Feature tests (skip database-dependent tests)
php phpunit.phar --testsuite Feature --exclude-group database

# With coverage
php phpunit.phar --coverage-html storage/coverage
```

## REST API

Full API documentation is available at [`docs/api.md`](docs/api.md).

| Endpoint | Description |
|----------|-------------|
| `GET /api/samples` | List samples |
| `POST /api/samples` | Create a sample |
| `GET /api/samples/{id}` | Get sample details |
| `GET /api/batches` | List batches |
| `GET /api/instruments` | List instruments |
| `POST /api/webhooks` | Register a webhook |

Authentication: Bearer token in `Authorization` header.

## Directory Structure

```
plexiq-lims/
├── app/                  # Core application (MVC)
│   ├── Controllers/      # Route handlers
│   ├── Helpers/          # Database, Auth, Audit helpers
│   ├── Middleware/        # Auth & API middleware
│   ├── Models/           # 50+ model classes
│   └── Services/         # Business logic (COA, SAP, Instruments)
├── config/               # Application configuration
├── database/             # Schema, migrations, seed data
├── docs/                 # Documentation & API reference
├── public/               # Web root
│   └── assets/           # CSS, JS, images
├── resources/views/      # PHP view templates
├── routes/               # Web & API route definitions
├── storage/              # Logs, COA exports, sessions
├── tests/                # Unit & Feature tests
├── docker-compose.yml    # Docker orchestration
├── Dockerfile            # Container build
└── phpunit.xml.dist      # Test configuration
```

## Architecture

PlexiQ LIMS uses a custom MVC framework with:

- **Router** — Regex-based routing with parameter extraction
- **BaseModel** — PDO-based ORM with CRUD, pagination, scopes, and relations
- **BaseController** — View rendering, JSON responses, redirects
- **Middleware** — Session auth + Bearer token API auth
- **CSRF Protection** — Token-based per-session
- **Audit Trail** — Automatic logging on all model mutations

## License

MIT — see [LICENSE](LICENSE).

---

Built with PHP, PostgreSQL, and Bootstrap 5.
