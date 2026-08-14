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
- Test result entry with specification limits

**Quality Management**
- OOS (Out-of-Specification) investigations
- CAPA (Corrective and Preventive Actions)
- Deviation management with action tracking
- SPC control charts (X-bar, R, Sigma)
- Stability studies with multi-timepoint tracking

**Regulatory Compliance**
- 21 CFR Part 11 electronic signatures & audit trail
- GDPR data privacy & consent logging
- HIPAA compliance controls
- Data retention policies
- Comprehensive audit logging

**Integrations**
- SAP HANA (OData + ODBC, bidirectional sync)
- Instrument file import (CSV, XML, text parsers)
- REST API with token authentication & webhooks
- SSO provider support
- Email notifications

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
5. Start the dev server (or double-click `serve.bat`):
   ```bash
   C:\xampp\php\php.exe -S 0.0.0.0:8080 -t public
   ```
   Use `0.0.0.0` instead of `localhost` to access the app from phones/tablets on the same network.
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

## Troubleshooting

| Symptom | Cause & Fix |
|---------|-------------|
| `'php' is not recognized` | PHP is not on the PATH. Use the full path (`C:\xampp\php\php.exe`) or add `C:\xampp\php` to your system PATH. |
| `could not find driver` | PostgreSQL drivers disabled. Uncomment `extension=pdo_pgsql` and `extension=pgsql` in `php.ini` and restart the server. |
| `database "limsdb" does not exist` | Create the database and load `database/schema.sql`, `database/seed_data.sql`, then the migrations. |
| `undefined table` / `undefined column` errors | Re-run all files under `database/migrations/` — several modules depend on them. |
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
