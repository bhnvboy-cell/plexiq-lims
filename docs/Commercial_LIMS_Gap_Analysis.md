# PlexiQ LIMS vs. Commercial LIMS — Comprehensive Gap Register

| Field | Value |
|-------|-------|
| System under analysis | PlexiQ LIMS (self-hosted, PHP 8.0+, PostgreSQL, custom MVC, no framework) |
| Comparator set | LabWare, Thermo Fisher SampleManager, STARLIMS, LabVantage, QBench / CloudLIMS (SMB tier) |
| Method | Full code review (`app/Controllers`, `app/Services`, `routes/web.php`, `routes/api.php`, `database/schema.sql`, all 11 SQL migrations, `docs/api.md`, live pg_dump in `storage/backups/`) + 2026 public LIMS feature write-ups |
| Date | 2026-08-14 |

**How to read this register:** Each gap has an ID, severity (🔴 Critical / 🟠 Major / 🟡 Moderate / 🔵 Minor), a description, evidence (`file:line`), and a recommendation. "PRESENT vs MISSING" is stated for every area so nothing is assumed. Where a module is advertised but only partially built, the gap explicitly calls out the stub.

---

## 0. Headline Findings (read first)

1. **91 tables** exist in the live DB, but **migration 014 (analysis parameters) is unapplied** while its UI exists → new installs fail on those pages.
2. **Two tables exist in live DB with no migration:** `manufacturers`, `webhook_logs` → fresh installs break code that uses them.
3. **Extensive schema-vs-code drift** — several controllers write columns that don't exist (API tokens, e-signatures, COA templates, deviations, supplier qualifications, training, translations, results). Several modules are **broken against their own schema**.
4. **Advertised features that are stubs or non-functional:** API token auth (broken end-to-end), SSO (config-only), e-signature (no content hash), rate limiting (unused), BI (SQL runner), plugins (DB registry), i18n (no adoption in views, English only), email (never sent anywhere), invoice PDF (HTML echo), SPC charts (none — only Cp/Cpk math), compliance scores (fabricated).
5. **No email is ever sent** by the app — notifications are DB rows only. This disables a whole class of advertised behavior (password reset, OOS alerts, COA delivery, reminders).
6. **No live/bidirectional instrument interfacing** — file import only; `host`/`port` fields are stored but never used.
7. **Cross-cutting engineering debt:** pervasive hardcoded `LIMIT` + missing pagination, no server-side validation in most controllers, destructive hard `DELETE`s on regulated records, audit trail not immutable, secrets stored in plaintext.

---

## 1. Compliance & Regulatory (the highest-stakes area)

### 🔴 C-01 — Electronic signatures are not 21 CFR Part 11 grade
- **PRESENT:** `electronic_signatures` table; `ESignatureController` records user, entity, reason, IP, UA.
- **MISSING:** No cryptographic binding to the record content (no SHA-256 of signed data is computed or stored despite a `signature_hash`/`signed_data` schema), no digital certificate, no timestamping authority, no "signing invalidates after content change" verification.
- **Evidence:** `app/Controllers/ESignatureController.php:23` writes `signature_type`/`signature_reason`/`user_agent` columns that **don't exist** (schema `migrations/008:111-121` has `signature_hash`, `signed_data`) → the e-sign flow is broken; `verify()` at `:53-55` only checks the account is active.
- **Also:** approval actions in `TestResultController`/`AnalysisParameterController` do **not** require or invoke e-signature at all.

### 🔴 C-02 — No validation package / CSV support
- **PRESENT:** none.
- **MISSING:** IQ/OQ/PQ protocols, GAMP 5-aligned documentation, vendor validation statements, validated change-control process. Without these, PlexiQ is unqualified for FDA/EU GxP deployments out of the box.
- **Evidence:** no validation artifacts anywhere in `docs/`, `deploy/`, or repo root.

### 🔴 C-03 — Audit trail is not immutable or tamper-evident
- **PRESENT:** `audit_logs` (old/new JSONB, user, IP, UA) appended by `Audit::log` on model mutations; login history; audit UI.
- **MISSING:** hash-chain (`prev_hash`), DB triggers preventing UPDATE/DELETE on `audit_logs`, DB-level audit triggers (app-level only — direct DB edits bypass it), audit of who viewed audit.
- **Evidence:** `Audit.php:25-27` swallows audit write failures silently; schema.sql:224-235 has no immutability controls.

### 🟠 C-04 — No 2FA / MFA
- **PRESENT:** password + session auth, `session_regenerate_id` on login, CSRF tokens.
- **MISSING:** TOTP/U2F/WebAuthn, mandatory-2FA policies. Enterprise and compliance-first LIMS (LabWare, STARLIMS) require it.
- **Evidence:** `users` table has only `password_hash`.

### 🟠 C-05 — No fine-grained RBAC / permission matrix
- **PRESENT:** single `role_id` per user, role-name string checks (`Auth::requireRole('Admin')`).
- **MISSING:** per-user/per-group permissions, object-level access, permission audit, delegable e-sign signatories.
- **Evidence:** schema has no permission/policy tables.

### 🟠 C-06 — COA / results lack amendment & reason-code governance
- **MISSING:** COA amendment/reissue chain (`amendment_no`, `reason_code`, `supersedes`), spec versioning (`spec_version`, `effective_from/to`, `superseded_by`), amendment reason codes on results. Regulated labs require documented spec/result change control.
- **Evidence:** term scan — 0 matches for `amendment`, `spec_version`, `reason_code` across schema/migrations.

### 🟠 C-07 — Hard deletes on regulated records
- **MISSING:** soft-deletes (`deleted_at`) anywhere; several controllers run destructive `DELETE`.
- **Evidence:** `BatchController.php:318` deletes `sample_tests`; `NotebookController` deletes ELN entries; `MasterDataController.php:908` deletes manufacturers; no `deleted_at` in any schema.

### 🟠 C-08 — Compliance module scores and enforcement are fabricated/incomplete
- **PRESENT:** GDPR export (real), GDPR anonymize (real), retention-policy CRUD, consent/privacy logs.
- **MISSING:** retention enforcement (no job executes `action_on_expiry`), consent capture workflow (no writer), privacy-log producer, HIPAA access auditing/encryption controls, right-to-erasure data-classification map.
- **Evidence:** `ComplianceController.php:40-51` — scores are `min(100, count(privacy_logs)*5)`; nothing writes `privacy_logs`/`consent_logs` beyond seeds.

### 🟠 C-09 — No measurement uncertainty (ISO 17025)
- **MISSING:** expanded uncertainty, k-factor, confidence intervals on results; method validation records (accuracy/precision/linearity/LOD-LOQ/recovery/robustness); proficiency-testing module; uncertainty tracking on methods.
- **Evidence:** `uncertainty` exists only on `calibration_records` (migrations/008:603); 0 matches for `proficiency`.

### 🟡 C-10 — Chain of custody is minimal
- **PRESENT:** barcode lookup + scan log + labels.
- **MISSING:** custody-transfer records, `received_by`/`received_from`/`shipped_via`/temperature-on-arrival, sample disposal/retention scheduling, sealed/returned-sample tracking, CoC report.

### 🟡 C-11 — Sample storage & retention not modeled
- **MISSING:** storage location/condition/temperature on samples, retention days, retention-sample scheduling, container type, sub-sample/aliquot/consumption tracking.

### 🔵 C-12 — ELN entries are not immutable / not signable
- **PRESENT:** editable/deletable free-text entries, audit log.
- **MISSING:** entry version history, witness/co-signature, digital signature on entries, section templates, search across content.
- **Evidence:** `NotebookController.php:116-161` update/delete; attachments model never referenced by controller.

---

## 2. Instrument Integration & Lab Automation

### 🔴 I-01 — No live / bidirectional instrument interfacing
- **PRESENT:** file import only (CSV/XML/TSV/TEXT) via upload and watch-folder polling; column→parameter mapping; conversion factor; auto-OOS; raw-row audit; async import jobs.
- **MISSING:** ASTM E1381/E1394, CLSI LIS2-A2, RS-232, IP/serial, bidirectional command download, CDS links (Chromeleon, Waters Empower). `host`/`port` fields are stored but never used.
- **Evidence:** `InstrumentController.php:37-38`; `InstrumentImportService.php` parsers only; no transport/driver layer.

### 🟠 I-02 — No SDMS
- **MISSING:** raw data archive, instrument file repository with metadata, re-analysis from raw data, PDF/Chromatogram archival, scientific data management.

### 🟠 I-03 — No LES (Laboratory Execution System)
- **MISSING:** step-by-step SOP-guided execution at the bench, procedural history capture, out-of-sequence prevention.

### 🟠 I-04 — No instrument scheduling / workload balancing
- **MISSING:** run calendars, instrument availability, worklist assignment, workload balancing across analysts/instruments.

### 🟠 I-05 — No instrument qualification & maintenance
- **MISSING:** IQ/OQ/PQ qualification records, preventive-maintenance schedules, work orders, breakdown logs, spare parts, calibration drift/trend analysis (records store `as_found`/`as_left`/`uncertainty` in schema but controller never reads/writes them — migrations/008:600-611 vs `CalibrationEnhancedController.php:218-228`).

### 🟡 I-06 — No equipment / instrument raw-data integrity chain
- **MISSING:** hash of raw instrument files, source-file lineage to result, dedupe beyond `source_file` name.

### 🟡 I-07 — Reference standards usage not tracked
- **MISSING:** usage log linking reference standards to instruments/runs; traceability certificate attachment (schema has `certificate_file`, unused).

### 🟡 I-08 — No sensor/logger ingestion for environmental monitoring
- **PRESENT:** manual reading POST + threshold alerts.
- **MISSING:** data-logger/sensor import, excursion-duration tracking, alert escalation to deviation/CAPA.

---

## 3. Quality Modules — depth gaps

### 🟠 Q-01 — No QC control-chart module (Levey-Jennings / Westgard)
- **PRESENT:** SPC computes Cp/Cpu/Cpl/Cpk/Cpm for production data (correct math).
- **MISSING:** daily QC module — Levey-Jennings charts, Westgard multi-rules, QC control lots/control materials, QC schedules, corrective action on QC failure, control-limit recalculation. **ISO 17025 staple, absent entirely.**
- **Evidence:** `SpcReading.php:44-83` (capability only); no chart rendering library in views; 0 matches for `control_lot`/`westgard`/`levey`.

### 🟠 Q-02 — No SPC control charts or run rules
- **MISSING:** X-bar/R, I-MR, p/np, u/c charts; Western Electric / Nelson run rules; out-of-control flags; trend detection; subgrouping. SPC is statistics-only with no visual charts.

### 🟠 Q-03 — OOS workflow lacks phase-gate structure
- **PRESENT:** CRUD + investigate/review/close status flips; auto-create from analysis workflow (severity hardcoded `'Major'`).
- **MISSING:** Phase 1/2 laboratory-investigation stages (USP <1010>/MHRA), root-cause taxonomy codes, investigation timeline/activity log, attachments, link to CAPA creation, escalation rules, transition validation.
- **Evidence:** `AnalysisParameterService.php:240` hardcodes severity; `OosController.php` has no transition enforcement.

### 🟠 Q-04 — CAPA lacks task-level tracking & effectiveness workflow
- **PRESENT:** CAPA CRUD + status updates.
- **MISSING:** individual action items with assignees/due dates, effectiveness-verification stage gating, SLA due-date alerts, recurrence checks, escalation.

### 🟠 Q-05 — Deviation lacks impact/disposition assessment & CAPA handoff
- **PRESENT:** CRUD + action tracking with assignees/due dates.
- **MISSING:** GMP-impact/product-impact/batch-disposition fields, recall linkage, auto-CAPA creation, approval-before-close, transition validation (`update` accepts arbitrary status).

### 🟠 Q-06 — Retest/rerun not audit-compliant
- **PRESENT:** `BatchController::retest`.
- **MISSING:** **`retest()` hard-deletes the prior result** (`BatchController.php:288-308`) instead of creating a retest revision with reason — destroys audit evidence. Also no retest-result linkage table, no replicate/parallel-analysis (n-value) grouping, no outlier rules (USP <1210>).

### 🟡 Q-07 — Stability lacks trend evaluation & scheduling
- **MISSING:** OOT (out-of-trend) regression/trend analysis for shelf-life, automatic timepoint due scheduling/notifications, chamber/pull/return tracking, stability-indicating assay flags, role separation (any user can close a study — `StabilityController` uses `requireAuth` only).

### 🟡 Q-08 — Environmental lacks monitoring plans & microbiology
- **MISSING:** scheduled monitoring plans (frequency/roster), settle-plate/media tracking, alert/action classification tiers, alert escalation.

---

## 4. Core LIMS Functional Gaps (samples/batches/results/COA)

### 🟠 F-01 — Results lack uncertainty & analyst/instrument binding
- **PRESENT:** numeric+text results, revision history (`result_revisions`), spec validation, per-product spec limits, enter/review/approve stamps.
- **MISSING:** per-result uncertainty, technician & instrument association on the result row, replicate grouping, spec version snapshot at entry time.

### 🟠 F-02 — Batch release lacks disposition codes
- **MISSING:** release/disposition decisions (released/conditional/quarantine), release reason codes, released-by stamps with justification. Batch workflow flips status with no side effects (`BatchController.php:167-192`), unlike samples.

### 🟠 F-03 — COA lacks revision/reissue & archival
- **PRESENT:** best-in-codebase PDF pipeline (TCPDF, watermark, QR/Code39, signature lines, pass/fail highlighting), release/revoke, customer portal.
- **MISSING:** COA version/reissue chain (no "Supersedes"/amendment numbering), document-level approval state (jumps Draft→Released; reviewer names come from sample not doc), PDF archival (regenerated on each download — no stored artifact or hash), offline QR/barcode in HTML preview (calls **external APIs** qrserver.com / barcode.tec-it.com — network-dependent, data-leak risk).
- **Evidence:** `CoaService.php:50-56` external APIs; `CoaDocument::findBySample` returns only latest; `CoaController.php:114-123`.

### 🟡 F-04 — Methods are name-only, unversioned
- **MISSING:** method details (steps, references), method versioning/revision, method approval workflow, method validation data, per-method limit-test data management.

### 🟡 F-05 — No data migration / import tooling
- **MISSING:** legacy-LIMS/spreadsheet migration wizard, data mapping, cleanse/dedupe, import validation reports.

### 🔵 F-06 — Analyst assignment not enforced at result entry
- **MISSING:** `TestResultController::saveResult` never verifies the assignee is the one entering; no per-sample worklist assignment workflow.

### 🔵 F-07 — Sample type workflows & required-field validation absent
- Sample types are master data only; no per-type required-field rules; free-form `$_POST` dates/priority with no server-side validation.

---

## 5. Platform / Integration / Security Gaps

### 🔴 P-01 — API token authentication is broken end-to-end
- **PRESENT:** `api_tokens` table, `ApiAuthMiddleware` (sha256 token lookup, active/expiry check), token-management UI.
- **MISSING:** **`createToken` inserts non-existent columns** (`token`, `masked_token`, `name` — `ApiIntegrationController.php:27-33`) while middleware reads `token_hash` → INSERT fails and **no code ever writes `token_hash`**. General API (`/api/*`) cannot authenticate. Also `docs/api.md` documents many endpoints that are **not routed** (see §7).
- **Evidence:** `ApiIntegrationController.php:27-43` vs `migrations/008:181-191` vs `ApiAuthMiddleware.php:21-31`.

### 🟠 P-02 — No rate limiting despite documentation
- **MISSING:** `api_rate_limits` table + model exist but are never invoked; no middleware, no `429` responses. Documented in `docs/api.md:411-424` only.

### 🟠 P-03 — Secrets stored in plaintext
- **MISSING:** encryption at rest. `sap_config` values plain TEXT with `is_encrypted` a **flag only** (schema.sql:256-262, seed at :627); `sso_providers.client_secret`/`ldap_bind_password`/`certificate` plain; `api_webhooks.secret_key` plain. `email_configurations.smtp_password` is bcrypt-hashed (can't be decrypted for SMTP → unusable).
- **Evidence:** no `openssl_encrypt` usage anywhere (0 matches).

### 🟠 P-04 — BI module is a raw-SQL runner (stub)
- **PRESENT:** saved reports with `query_sql`, run executes SQL, connection registry with fake test.
- **MISSING:** validation of stored SQL (privilege/security risk — create/edit only require `Auth::requireAuth()`, `BiAnalyticsController.php:27,37,55-58`), export formats, charts, scheduling/delivery, pre-aggregation/OLAP. `testConnection` returns a canned string, no real test (`:74-84`).

### 🟠 P-05 — SSO is config-only (no authentication)
- **PRESENT:** stores LDAP/OAuth/SAML settings with audit.
- **MISSING:** any actual SAML assertion / OIDC token exchange / LDAP bind in the login path; `testConnection` literally "Simulates connection test - in production would use a HTTP client" (`SsoController.php:129`).
- **Evidence:** `SsoController.php:119-139`; login path (`Auth`, `AuthController`) never references SSO.

### 🟠 P-06 — No email delivery anywhere
- **PRESENT:** SMTP config UI, notification templates, per-user notification settings, DB notifications.
- **MISSING:** **no email is ever sent** — no mailer integration. NotificationController.php:121 "actual email would use a mailer service"; MasterDataController.php:826 "Test email functionality requires PHPMailer...". Disables OOS alerts, approvals, COA delivery, reminders.
- **Evidence:** grep for mail/PHPMailer/smtp send — zero send calls.

### 🟠 P-07 — Plugin system is a DB registry, not an architecture
- **MISSING:** no plugin code loading, no hook dispatch (the `plugin_hooks` table + `PluginHook` model are referenced **nowhere**), no manifest/versioning/sandbox/marketplace. `PluginController` only inserts/toggles/deletes rows.
- **Evidence:** `PluginController.php:19-52`; `plugin_hooks` never read/written.

### 🟠 P-08 — i18n is not implemented in the UI
- **PRESENT:** language CRUD, translation-key CRUD, session switch, JSON export.
- **MISSING:** **only English seeded** (migrations/008:614-615), `__()` helper used by **zero views** (UI hardcoded English), `Translation::translate()` queries wrong columns (`key`/`value` vs `translation_key`/`translation_value` — dead code).
- **Evidence:** grep across `resources/views` for `__(` returns nothing.

### 🟠 P-09 — Limited API surface + docs mismatch
- **MISSING:** documented endpoints not routed (`PUT /api/samples/{id}`, status transitions, batches, COA, instruments, webhooks, notifications read, `/api/barcode/{code}` etc.); API returns flat arrays, not paginated envelopes; no OpenAPI spec, no versioning, no per-resource token scopes (permissions JSONB unused by routes); CSRF is skipped for `/api/` while auth is broken (P-01).

### 🟠 P-10 — Webhook delivery depends on a manually-run worker
- **PRESENT:** real async queue (`jobs`, `DeliverWebhook`, HMAC-SHA256 signing, retries, logs) — genuinely good.
- **MISSING:** `webhook_logs` table has **no migration** (live-DB only) → fresh install breaks delivery; `bin/worker.php` must be run manually (documented but not scheduled).

### 🟡 P-11 — SAP sync has functional defects
- **PRESENT:** genuinely production-grade ETL (OData client, Basic auth, push/pull, retry loop, ODBC fallback, sync log).
- **MISSING/FIX:** CSRF token extraction reads `CURLINFO_HEADER_OUT` (request headers) instead of response headers → token never captured (`SapSyncService.php:392-412`); hardcoded `LIMIT 50` per batch (`:87,139,175`); results use timestamp watermark (no per-row sync status); secrets plaintext (P-03).

### 🟡 P-12 — Barcode/label claims exceed implementation
- **PRESENT:** lookup across 4 entities, scan log, label print.
- **MISSING:** scan page is a plain text input (no camera/scanner integration); labels use **JsBarcode from CDN** (offline failure; CODE128 only — QR/Data Matrix/EAN-13 claims unsupported); no ZPL/BarTender printer drivers; no container check-in/out actions.
- **Evidence:** `resources/views/barcode/scan.php:15-21`, `print-label.php:31-39`.

### 🟡 P-13 — Invoicing gaps
- **PRESENT:** invoice CRUD, line items, payment recalc, tax/discount fields.
- **MISSING:** invoice PDF is an HTML echo stub (`BillingController.php:198-222` "In production, use a PDF library"), tax-rate/VAT/currency management, credit notes/voids, payment gateway, receipts/dunning, discount not applied in totals (`:159-165`).

### 🟡 P-14 — Backup is strong but lacks encryption/offsite
- **PRESENT:** the most production-ready service — pg_dump/psql, SHA-256 manifest, retention, restore with ON_ERROR_STOP, path-traversal guard.
- **MISSING:** encrypted backups, off-site/cloud target config, restore audit trail.

### 🔵 P-15 — Client portal is read-only
- **PRESENT:** self-registration, login, COA view/PDF for owning customer.
- **MISSING:** online sample/order submission, order & status visibility, portal activity/consent trail, reports beyond COA.

---

## 6. Cross-Cutting Engineering Debt (applies to every module)

| ID | Issue | Evidence |
|----|-------|----------|
| X-01 | **No pagination / hardcoded LIMITs** on many lists — full scans as data grows | `LIMIT 200`: BillingController.php:46,113; UserController:96; Compliance:95,109; Barcode:79; ESignature:67; Environmental:140. `LIMIT 500`: Environmental:82; Language:21. `LIMIT 100`: MasterData:490,551. `LIMIT 50`: SapSync:87,139,175; Training:28; Compliance:21,28; BiAnalytics:20; ApiIntegration:147. `LIMIT 10`: CalibrationEnhanced:37,46,55; Dashboard:22. **None at all:** Oos::index, Capa::index, Supplier::index, Instrument results, Environmental index/alerts, Training courses, Spc index, Plugin index |
| X-02 | **No server-side validation** in most controllers — raw `$_POST` for dates/priorities/codes | SampleController.php:74-90 and throughout |
| X-03 | **Schema/code drift** — controllers write non-existent columns | API tokens (P-01), e-signatures (C-01), COA templates (MasterDataController:648 vs coa_templates schema), deviations (`DeviationController:50` writes `deviation_number`, `batch_number`, `immediate_action` — table has `deviation_code`), supplier qualifications (`SupplierController:135` vs 008:493-506), training (`TrainingController:56` vs 008:424-438), translations, samples `source`/`created_by` (API path), results spec columns (API path) |
| X-04 | **Dropped tables still modeled** | `EnvMonitoringPoint`/`EnvMonitoringReading` models reference tables dropped in migrations/010:6-7 |
| X-05 | **Migration 014 unapplied** yet referenced by UI | `analysis_parameters` family absent from live DB; missing on fresh install |
| X-06 | **Missing migrations for live tables** | `manufacturers` (MasterDataController:850), `webhook_logs` (DeliverWebhook:56) |
| X-07 | **Dead code / bugs** | `TestResultController.php:229` boolean→`$sampleId`; `AnalysisParameterService.php:278` queries `full_name` from `samples` (no such column); `Translation::translate()` wrong columns; `SapSyncService` CSRF (P-11); COA insert fails if no default template (`CoaController.php:79`) |
| X-08 | **No automated test coverage of workflows** | 9 PHPUnit files, mostly unit; no integration harness, no CI/CD pipeline |
| X-09 | **No security certifications / pen-test reports** | SOC 2 / ISO 27001 alignment absent; commercial vendors publish these |
| X-10 | **No mobile / offline capture** | No PWA/native app; enterprise vendors ship iOS/Android or tablet LES |
| X-11 | **No multi-site / multi-tenant** | No org/site dimension or tenant isolation |
| X-12 | **No no-code configurator** | Workflows/forms/fields defined in code or raw master rows; no drag-drop workflow/status/form designer (Matrix Gemini, QBench, LabVantage differentiator) |
| X-13 | **No formal document management** | No versioned, approval-controlled SOP/document store (ELN is notebooks, not controlled docs) |
| X-14 | **No cloud/SaaS offering** | Self-host only; commercial market has shifted cloud-first |

---

## 7. API Docs vs Reality (docs/api.md vs routes/api.php)

**Actually routed (routes/api.php:12-32):** `POST /api/sap/push/sample`, `POST /api/sap/push/result`, `GET /api/sap/pull/{customer,product,specification}`, `GET /api/sap/status`, `GET /api/status` (public), `GET /api/samples`, `GET /api/samples/{id}`, `POST /api/samples`, `GET /api/results`, `POST /api/results/{sampleTestId}`, `GET /api/products`, `GET /api/customers`, `GET /api/notifications`, `GET /api/notifications/unread`, `GET /api/barcode/lookup`.

**Documented but NOT routed:** `PUT /api/samples/{id}` (api.md:166), `PUT /api/samples/{id}/status` (:182), `GET /api/samples/{id}/tests` (:217), `GET /api/results/pending` (:239), batches endpoints (:249-262), COA endpoints (:271-278), instruments (:287-305), `POST /api/product-tests` (:326), `POST /api/sap/sync/samples` (:358), `PUT /api/notifications/{id}/read` (:380), webhooks (:390-407).

**Behavior mismatches:** documented `per_page` vs actual `limit` (cap 100); documented filters (status/customer/product/priority/search) **ignored** (`GeneralApiController.php:31-37`); documented `GET /api/barcode/{code}` vs actual `GET /api/barcode/lookup?barcode=`; `POST /api/results/{sampleTestId}` differs from documented `POST /api/results`; `GET /api/notifications/unread` returns a count, docs imply a list.

**Auth:** Bearer token → sha256 lookup; CSRF skipped for `/api/`; **token creation broken (P-01)** so the whole API is effectively unusable for auth'd routes.

---

## 8. Realistic Competitive Positioning

| Tier | Systems | PlexiQ position |
|------|---------|-----------------|
| Enterprise / global regulated | LabWare, STARLIMS, SampleManager, LabVantage | **Not competitive** — instrument interfacing, validation packages, configurability, SSO/MFA, SDMS/LES, mobile, support/SLA, and even basic API auth all absent or stubbed |
| SMB testing / diagnostics | QBench, CloudLIMS, LABWORKS | **Feature-breadth competitive**; loses on instrument connectivity, SaaS hosting, 2FA, working email, and vendor support |
| Open-source | SENAITE, OpenLabFramework, ELabFTW | **PlexiQ is likely behind SENAITE** — SENAITE ships real instrument interfaces (ASTM) and ISO 17025-oriented tooling with an active community |

**Honest verdict:** PlexiQ's **module breadth** (OOS, CAPA, deviation, stability, environmental, SPC, calibration, training, billing, ELN, client portal, i18n, plugins, SAP, backup) is impressive for a self-hosted codebase and beats many SMB SaaS LIMS on *feature count*. But **depth, reliability, and compliance-grade controls** are materially below the commercial tier, and several advertised capabilities are broken or stubbed. It is best positioned as a **mid-tier self-hosted QC/QA LIMS for a single site**, with the roadmap below needed to close the most damaging gaps.

---

## 9. Recommended Roadmap (priority order)

### Phase 0 — Stabilize what exists (bug fixes, no new features)
1. Fix schema/code drift: API token create→`token_hash`, e-signature columns, COA template columns, deviations, supplier qualifications, training, translations, samples/results API columns.
2. Ship missing migrations (`manufacturers`, `webhook_logs`) and apply 014 on fresh installs; reconcile dropped env tables.
3. Fix `TestResultController:229`, `AnalysisParameterService:278`, `SapSyncService` CSRF, `BillingController` discount, `CoaController` default-template fallback, `Translation::translate()`.

### Phase 1 — Compliance hardening (to legitimately claim 21 CFR Part 11 / ISO 17025)
4. **E-signature:** compute & store SHA-256 of signed record content, timestamp, verify-on-content-change; wire into result/COA approval actions.
5. **Immutable audit:** hash-chain `audit_logs`, DB triggers blocking UPDATE/DELETE, DB-level audit triggers, fail-closed logging.
6. **SSO:** implement real LDAP bind, OIDC authorization-code, and SAML HTTP-POST flows (replace the simulated test).
7. **2FA/TOTP** with mandatory-enforcement policy.
8. **Email:** integrate a real mailer (PHPMailer/Symfony Mailer); deliver notifications, OOS alerts, COA, reminders.
9. **QC control module:** Levey-Jennings + Westgard multi-rule, control lots, schedules, corrective actions.
10. **Publish validation kit:** IQ/OQ/PQ templates + GAMP-5-aligned CSV guidance.
11. Soft-deletes + audit-compliant retest (revision, not delete) across regulated entities; spec/COA amendment & reason-code versioning.

### Phase 2 — Lab operations
12. **Bidirectional instrument interface:** ASTM E1381/E1394 + RS-232/IP transport, 2–3 protocol drivers first; keep file import as fallback.
13. **LES-lite:** guided step-by-step result entry with sequencing and procedural capture.
14. **Instrument scheduling** + workload board.
15. **SPC charting:** X-bar/R, I-MR with Nelson/Western Electric rules and dashboard alerts (data & Cp/Cpk math already exist).
16. **Chain of custody:** custody transfers, disposal/retention logging, CoC report.
17. **Measurement uncertainty** on results + method validation records + proficiency-testing module (ISO 17025).
18. Mobile-responsive PWA / offline bench capture.

### Phase 3 — Platform & ecosystem
19. **Real plugin architecture** (hook dispatch, manifests, versioning) or remove the claim.
20. **Real i18n** — translate the UI, seed languages, fix translation schema helper.
21. **Fix + expand REST API** — OpenAPI spec, versioning, per-resource scopes, functional token flow, rate-limit enforcement, paginated envelopes.
22. **Ad-hoc reporting** — export formats, scheduled delivery, dashboards with charts.
23. **No-code configurator** — workflow/status/form designer stored as data.
24. **Multi-site/tenant** dimension + central admin.
25. **Security** — SOC 2 / ISO 27001 alignment, pen-test report, encrypt secrets at rest (SAP, SSO, webhooks).
26. **Cloud deployment option** — managed SaaS or 1-click cloud install (docker-compose exists).

---

## 10. Appendices

### A. Confirmed implemented vs stubbed (module depth summary from code review)

| Module | Depth | Verdict |
|--------|-------|---------|
| TestResult workflow | Functional workflow | Closest to commercial parity (minus e-sign, replicates, pagination) |
| Analysis Parameters | Functional workflow | Real validation + auto-OOS + SPC feed (migration 014 unapplied though) |
| COA | Functional + TCPDF | Best output pipeline; missing revision/reissue, PDF archival, offline QR |
| SAP Sync / Backup | Production-grade | Genuinely enterprise-quality ETL + backup tooling (CSRF bug, plaintext secrets) |
| Instruments | File-import only | No live interface/drivers — biggest gap vs LabWare/STARLIMS |
| SPC | Statistics only | Cp/Cpk math real; zero charting/control rules |
| OOS/CAPA/Deviation | CRUD + light workflow | No enforcement, escalation, or phase-gate structure |
| ELN | Basic CRUD | Not a real ELN (editable/deletable, no witnessing, no versioning) |
| Compliance | Partial | Export/anonymize real; enforcement + scores fake |
| Billing | CRUD | No PDF, no currency/tax mgmt, no payment gateway |
| BiAnalytics | Stub | SQL-runner UI; no export/scheduling/charts; stored-SQL risk |
| Plugin | Stub | DB registry only; hooks never wired |
| i18n | Stub | Helper + admin UI, zero adoption in views, English only |
| API integration | Broken | Token auth cannot work end-to-end; no rate limiting |
| Notifications | DB rows only | No email delivery anywhere |
| Environmental | Functional | Real threshold alerts; no logger integration, hardcoded LIMITs |
| Calibration | Functional + scheduling | Schema richer than controller; no pass/fail tolerance, no due notifications |

### B. Confirmed strengths (fair credit)
- **Workflow engines** in TestResults, AnalysisParameters, and SampleController (validated transition tables, revision snapshots, auto-OOS, SPC feed).
- **COA PDF** — real TCPDF with watermark/QR/barcode/signature/pass-fail highlighting.
- **SAP HANA sync** — real OData/ODBC ETL with retries and sync logs.
- **Backup service** — pg_dump/psql, SHA-256 manifests, retention, guarded restore.
- **Async webhooks** — HMAC-signed deliveries, retries, DB queue.
- **Security hygiene** — bcrypt hashing, session regeneration, CSRF tokens, parameterized SQL, token auth design.
- **Scalability groundwork** — DB sessions, file/db caches, performance-index migration, pagination on flagship lists.

### C. Comparison sources
Public 2026 feature write-ups: LabSoftwareGuide "Best LIMS Software 2026", PharmaNow 2026 buyer guide, G2 vendor comparisons, LabManager LIMS guide, LIMSWiki requirements lists/LIMSpec. Vendor capability claims are indicative, not verified against specific versions.

### D. Evidence index (key file references)
- Schema: `database/schema.sql`, migrations `database/migrations/*.sql`
- API: `docs/api.md`, `routes/api.php`, `app/Controllers/Api/GeneralApiController.php`, `app/Controllers/Api/SapApiController.php`, `app/Middleware/ApiAuthMiddleware.php`
- Stubs/bugs: `SsoController.php:129`, `BillingController.php:198-222`, `BiAnalyticsController.php:55-58,74-84`, `ComplianceController.php:40-51`, `ESignatureController.php:23`, `ApiIntegrationController.php:27-43`, `TestResultController.php:229`, `SapSyncService.php:392-412`, `AnalysisParameterService.php:278`, `CoaService.php:50-56`
