# UAT Test Script — PlexiQ LIMS

| Field | Value |
|-------|-------|
| System under test | PlexiQ LIMS (Laboratory Information Management System) |
| Test phase | User Acceptance Testing (UAT) |
| Environment | UAT (copy of production data, seeded users) |
| Base URL | http://localhost:8080 (or UAT host) |
| Prepared by | |
| Prepared date | |
| Test start date | |
| Test end date | |
| Approved by | |

## Default test accounts

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `admin@123` |
| Analyst | `analyst` | `admin@123` |
| Reviewer | `reviewer` | `admin@123` |
| Approver | `approver` | `admin@123` |
| Customer | `customer` / `customer1` | `admin@123` |

## How to use

1. Execute each test case in order.
2. Record the result in **Result**: `PASS`, `FAIL`, or `N/A`.
3. For FAIL: attach evidence (screenshot / steps to reproduce) in **Notes**.
4. Sign off at the end of each module.

Legend — **Pre**: preconditions, **Steps**: steps to perform, **Expected**: expected behavior.

---

## Module 1 — Authentication & Access Control

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| AUTH-01 | Valid admin login | App installed | 1) Open `/login` 2) Enter `admin` / `admin@123` 3) Submit | Redirect to dashboard; user name shown; login recorded in audit trail | | |
| AUTH-02 | Invalid password | None | 1) Open `/login` 2) Enter `admin` / wrong password 3) Submit | Error message shown; login denied; failed attempt logged in login history | | |
| AUTH-03 | Empty required fields | None | 1) Submit login form with blank fields | Validation error displayed; no session created | | |
| AUTH-04 | Logout | Logged in | 1) Click logout link | Returned to `/login`; back button cannot access dashboard without re-login | | |
| AUTH-05 | Guest access blocked | Logged out | 1) Open `/dashboard`, `/samples`, `/coa` while logged out | Redirected to `/login` (auth middleware) | | |
| AUTH-06 | Profile page | Logged in | 1) Open `/profile` 2) Update profile fields 3) Save | Profile saved successfully | | |
| AUTH-07 | Password change | Logged in | 1) Change password to new value 2) Logout 3) Login with new password | Login succeeds with new password | | |
| AUTH-08 | CSRF protection | Logged in | 1) Submit a POST form with missing/invalid CSRF token | Request rejected (error/403) | | |

**Module 1 sign-off:** Tester: ______ Date: ______

---

## Module 2 — Dashboard

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| DASH-01 | Dashboard loads | Logged in as admin | 1) Open `/` or `/dashboard` | Dashboard renders with stats (samples, batches, pending tests, OOS, COA) and recent samples | | |
| DASH-02 | Dashboard cached stats | Logged in | 1) Reload dashboard twice | Second load served from cache (60s); stats still accurate after cache invalidation | | |
| DASH-03 | Dashboard customization | Logged in | 1) Open `/dashboard/customize` 2) Add/remove/reset widgets 3) Save | Widgets persist per user; reset restores defaults | | |
| DASH-04 | Dashboard filters | Logged in | 1) Open `/dashboard/filters` 2) Save a filter 3) Delete a saved filter | Filters saved and deleted correctly | | |
| DASH-05 | Role-based dashboard content | Log in as analyst/reviewer/customer | 1) Check dashboard content per role | Content appropriate to role; customer sees client portal instead | | |

**Module 2 sign-off:** Tester: ______ Date: ______

---

## Module 3 — Master Data Management (Admin)

### 3.1 Customers
| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| MSTR-01 | Create customer | Admin | 1) `/master/customers` 2) Create new customer with required fields 3) Save | Customer appears in list with correct data | | |
| MSTR-02 | Edit customer | Customer exists | 1) Edit an existing customer 2) Change details 3) Save | Changes persisted and reflected in list | | |
| MSTR-03 | Delete customer | Customer exists (unused) | 1) Delete a customer | Customer removed; audit trail records deletion | | |
| MSTR-04 | Search master data | Data exists | 1) Use `/master/search` | Results filtered by keyword across tables | | |

### 3.2 Products, Tests, Methods, Units, Sample Types
| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| MSTR-05 | Create product | Admin | 1) `/master/products` 2) Add product (e.g. Starch) 3) Save | Product created and listed | | |
| MSTR-06 | Create test parameter | Admin | 1) `/master/tests` 2) Add test with spec limits/method 3) Save | Test created with limits shown | | |
| MSTR-07 | Create method (tile format) | Admin | 1) `/master/methods` 2) Add method tile 3) Save; then edit via JSON editor | Method saved; JSON edit round-trips without data loss | | |
| MSTR-08 | Create unit | Admin | 1) `/master/units` 2) Add unit (e.g. `%`, `ppm`) | Unit appears in unit list | | |
| MSTR-09 | Create & toggle sample type | Admin | 1) `/master/sample-types` 2) Add sample type 3) Toggle active/inactive | Sample type saved; toggled off hides it from selection | | |

### 3.3 Product–Test Mapping
| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| MSTR-10 | Assign tests to product | Product + tests exist | 1) `/master/product-tests` 2) Map tests to product with spec limits 3) Save 4) Edit 5) Delete | Mapping saved, edited, and deleted correctly | | |

### 3.4 Export
| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| MSTR-11 | Export master table | Data exists | 1) Open `/master/export/{table}` | CSV/file downloads with correct rows | | |

**Module 3 sign-off:** Tester: ______ Date: ______

---

## Module 4 — Batch Management

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| BATCH-01 | Create batch | Admin/Analyst, product exists | 1) `/batches` 2) New Batch 3) Enter batch number e.g. `STARCH-2026-001`, product, dates 4) Save | Batch created with status `Registered` | | |
| BATCH-02 | Duplicate batch number | Batch exists | 1) Try to create batch with same number | Duplicate rejected with error | | |
| BATCH-03 | Edit batch | Batch exists | 1) Edit batch details 2) Save | Changes saved and audit-logged | | |
| BATCH-04 | Add sample to batch | Batch exists | 1) Open batch 2) Add sample(s) | Samples associated with batch; count updated | | |
| BATCH-05 | Add tests to batch | Batch exists, product mapped | 1) Add tests (auto-suggested from product mapping) | Tests assigned to batch samples | | |
| BATCH-06 | Batch workflow | Batch exists | 1) Move batch through workflow (registered → testing → in-review → approved) | Status transitions correctly and are logged | | |
| BATCH-07 | Retest / remove test | Sample test exists | 1) Retest a sample test 2) Remove a sample test | Retest creates new result cycle; removal works | | |
| BATCH-08 | Print batch labels | Batch exists | 1) `/labels/batch/{id}` | Labels render and print correctly | | |
| BATCH-09 | Batch list pagination | >20 batches | 1) Open `/batches` | List paginates at 20 rows with prev/next controls and count | | |

**Module 4 sign-off:** Tester: ______ Date: ______

---

## Module 5 — Samples & Analysis

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| SMPL-01 | Create sample | Admin/Analyst | 1) `/samples/create` 2) Enter sample code, type, customer, product 3) Save | Sample created and listed | | |
| SMPL-02 | View sample detail | Sample exists | 1) Open `/samples/{id}` | All details, status, tests, results shown | | |
| SMPL-03 | Edit sample | Sample exists | 1) Edit sample 2) Save | Changes saved | | |
| SMPL-04 | Sample workflow | Sample exists | 1) Move sample through workflow | Status transitions correctly and logged | | |
| SMPL-05 | Assign tests to sample | Sample exists | 1) `/samples/{id}/assign-tests` 2) Assign tests | Tests appear on sample | | |
| SMPL-06 | Assign analysis parameters | Sample exists | 1) `/samples/{id}/parameters` 2) Assign parameters with spec limits 3) Save | Parameters assigned per sample | | |
| SMPL-07 | Enter parameter results | Parameters assigned | 1) `/samples/{id}/parameters/entries` 2) Record results 3) Save | Results recorded; status `Completed`; out-of-spec auto-creates OOS | | |
| SMPL-08 | Review parameter result | Analyst entered result | 1) POST `/analysis-results/{id}/review` | Status → `Reviewed`; audit trail updated | | |
| SMPL-09 | Approve parameter result | Reviewed | 1) POST `/analysis-results/{id}/approve` | Status → `Approved`; feeds SPC; notifications sent | | |
| SMPL-10 | Print sample label | Sample exists | 1) `/labels/sample/{id}` | Label renders with barcode/QR | | |
| SMPL-11 | Barcode scan & lookup | Sample with barcode | 1) `/barcode/scan` 2) Scan/lookup code | Sample found; scan logged in `/barcode/logs` | | |

**Module 5 sign-off:** Tester: ______ Date: ______

---

## Module 6 — Test Results & Approval Workflow

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| RES-01 | Pending results list | Tests without results | 1) `/tests/pending` | Lists only pending tests | | |
| RES-02 | Enter result | Pending test | 1) `/tests/{id}/result` 2) Enter value 3) Save | Result saved; spec comparison shown (pass/fail) | | |
| RES-03 | Out-of-spec detection | Enter result beyond limit | 1) Enter out-of-spec value 2) Save | Result flagged; OOS record auto-created; notified to Reviewer/Approver | | |
| RES-04 | Review results | Entered results | 1) `/tests/review` 2) Approve a result 3) Submit review | Status → Reviewed | | |
| RES-05 | Final approval | Reviewed results | 1) `/tests/final-approval` 2) Final approve | Status → Approved; eligible for COA | | |
| RES-06 | Rejection path | Entered/reviewed result | 1) Reject instead of approve | Result returns to previous state for correction | | |

**Module 6 sign-off:** Tester: ______ Date: ______

---

## Module 7 — COA (Certificate of Analysis)

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| COA-01 | Generate COA | Approved sample | 1) `/coa` 2) Generate COA for approved sample | COA generated with sample/test/result data, company header, QR/barcode | | |
| COA-02 | View COA | COA exists | 1) `/coa/{id}` | COA renders correctly in browser | | |
| COA-03 | Download COA PDF | COA exists | 1) `/coa/{id}/pdf` | PDF downloads; layout/format correct (dompdf) | | |
| COA-04 | Release COA | COA exists | 1) `/coa/{id}/release` | COA marked Released; visible to customer portal | | |
| COA-05 | COA template customizer | Admin | 1) `/master/coa-templates` 2) Create/edit template 3) Preview 4) Set as default | Template saved, previewed, default applied to new COAs | | |

**Module 7 sign-off:** Tester: ______ Date: ______

---

## Module 8 — Instrument Integration

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| INST-01 | Create instrument | Admin | 1) `/instruments/create` 2) Add instrument with watch folder/parser 3) Save | Instrument created and listed | | |
| INST-02 | Edit / delete instrument | Instrument exists | 1) Edit and save 2) Delete instrument | Edit persists; delete removes with audit log | | |
| INST-03 | Upload result file | Instrument exists | 1) `/instruments/{id}/import` 2) Upload CSV/XML/text file | File enqueued on `imports` queue; parsed by worker; raw rows in `instrument_results` | | |
| INST-04 | Column mapping | Instrument exists, parameters defined | 1) `/instruments/{id}/mappings` 2) Map source column → parameter (with conversion factor/unit) 3) Save 4) Delete mapping | Mapping saved/deleted; auto-import resolves sample by `sample_code`, applies conversion | | |
| INST-05 | Auto-fetch from watch folder | Watch folder configured | 1) Run `php bin/console instrument:scan` 2) Place a file in watch folder | New files detected and queued for import | | |
| INST-06 | Imported results list | Imports done | 1) `/instruments/imports` | Imported results listed with source file and status | | |
| INST-07 | Match result to sample | Imported result | 1) Match single result 2) Match all | Result matched to sample; shown on sample test result | | |
| INST-08 | Import deduplication | Same file re-imported | 1) Re-upload same file | Duplicate rows rejected via `source_file` dedupe | | |

**Module 8 sign-off:** Tester: ______ Date: ______

---

## Module 9 — OOS, CAPA & Deviations

### OOS
| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| OOS-01 | Create OOS | Out-of-spec result | 1) `/oos/create` 2) Create OOS linked to sample/result | OOS created (number `OOS-xxx`); Reviewer/Approver notified | | |
| OOS-02 | OOS investigation | OOS exists | 1) Open OOS 2) Add investigation notes 3) Submit | Investigation saved and dated | | |
| OOS-03 | OOS review | OOS investigated | 1) Review OOS | Review recorded | | |
| OOS-04 | Close OOS | OOS reviewed | 1) Close OOS with disposition | OOS closed; status `Closed` | | |
| OOS-05 | Edit / delete OOS | OOS exists | 1) Edit 2) Save 3) Delete | Edit persists; delete removes | | |

### CAPA
| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| CAPA-01 | Create CAPA | None | 1) `/capa/create` 2) Fill form (linked to OOS/deviation optional) 3) Save | CAPA created and listed | | |
| CAPA-02 | Update CAPA status | CAPA exists | 1) Open CAPA 2) Update status (open → in-progress → closed) | Status transitions persist and are logged | | |
| CAPA-03 | Edit / delete CAPA | CAPA exists | 1) Edit/save 2) Delete | Edit persists; delete removes | | |

### Deviations
| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| DEV-01 | Create deviation | None | 1) `/deviations/create` 2) Fill details 3) Save | Deviation created and listed | | |
| DEV-02 | Add action to deviation | Deviation exists | 1) Open deviation 2) Add action 3) Update action status | Action added; status change persisted | | |
| DEV-03 | Close deviation | Deviation with actions | 1) Close deviation | Deviation closed and logged | | |

**Module 9 sign-off:** Tester: ______ Date: ______

---

## Module 10 — Client Portal (Customer Self-Service)

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| PORT-01 | Customer login | Customer account seeded | 1) `/client/login` 2) Login as `customer` | Redirected to `/client/dashboard` | | |
| PORT-02 | Customer registration | None | 1) `/client/register` 2) Register a new customer account | Account created; can log in | | |
| PORT-03 | View COA as customer | Released COA exists | 1) Open `/client/coa/{id}` | COA visible to owning customer only | | |
| PORT-04 | Download COA PDF | Released COA | 1) `/client/coa/{id}/pdf` | PDF downloads correctly | | |
| PORT-05 | Access control | Customer account | 1) Try opening staff routes (`/samples`, `/coa`, `/master`) | Access denied / redirect (customer role isolation) | | |

**Module 10 sign-off:** Tester: ______ Date: ______

---

## Module 11 — Audit, Compliance & E-Signatures

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| AUD-01 | Audit trail view | Data changes exist | 1) `/audit` | Audit entries list user, action, entity, timestamp; filterable | | |
| AUD-02 | Login history | Login attempts exist | 1) `/audit/login-history` | Successful & failed logins recorded with IP/time | | |
| ESIGN-01 | Electronic signature | Result pending approval | 1) Sign an entity via `/esign/sign/{entityType}/{entityId}` | Signature recorded with user/time; verifiable at `/esign/verify/{id}` | | |
| ESIGN-02 | E-sign audit | Signatures exist | 1) `/esign/audit` | Signature audit list shows all signatures | | |
| COMP-01 | Data retention policy | Admin | 1) `/compliance/data-retention` 2) Add/edit/delete retention policy | Policies saved/deleted | | |
| COMP-02 | Privacy / consent logs | Data exists | 1) `/compliance/privacy-logs` and `/consent-logs` | Logs render | | |
| COMP-03 | Data export (GDPR) | User exists | 1) `/compliance/export/{userId}` | Export generated for the user | | |
| COMP-04 | Anonymize user (GDPR) | User exists | 1) `/compliance/anonymize/{userId}` | User data anonymized; consent log updated | | |

**Module 11 sign-off:** Tester: ______ Date: ______

---

## Module 12 — User & Notification Management

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| USER-01 | Create user | Admin | 1) `/users/create` 2) Add user with role 3) Save | User created; can log in with assigned role | | |
| USER-02 | Edit user / role | User exists | 1) `/users/{id}/edit` 2) Change role/status 3) Save | Changes persist; access reflects new role | | |
| NOTIF-01 | Notification list | Events exist | 1) `/notifications` | Notifications listed; unread highlighted | | |
| NOTIF-02 | Mark read / all read | Notifications exist | 1) Mark one read 2) Mark all read | Badge counts update | | |
| NOTIF-03 | Notification settings | Logged in | 1) `/notifications/settings` 2) Toggle channels/events 3) Save; send test | Settings saved; test notification delivered | | |

**Module 12 sign-off:** Tester: ______ Date: ______

---

## Module 13 — Additional Modules (Smoke Tests)

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| MOD-01 | ELN notebook & entries | Logged in | 1) Create notebook 2) Add entry (with attachment) 3) Edit entry | Notebook/entry CRUD works; attachment saved | | |
| MOD-02 | SPC control chart | Approved results exist | 1) `/spc` 2) Open detail 3) Calculate | Control chart (X-bar, R, Sigma) renders from data | | |
| MOD-03 | SPC reading entry | SPC parameter exists | 1) POST `/spc/{id}/readings` | Reading recorded and reflected in chart | | |
| MOD-04 | Stability study | Logged in | 1) Create study 2) Add timepoints 3) Record results 4) Close study | Study lifecycle works; results logged | | |
| MOD-05 | Environmental monitoring | Logged in | 1) Create point 2) Add reading 3) View alerts 4) Acknowledge alert | Points/readings/alerts work; acknowledged alert disappears | | |
| MOD-06 | Chemical inventory | Logged in | 1) Add chemical 2) Edit 3) Adjust stock | Chemical CRUD + stock adjustment works | | |
| MOD-07 | Calibration (enhanced) | Logged in | 1) Add standard 2) Create schedule 3) Add calibration record 4) Check overdue list | Standards/schedules/records work; overdue detected | | |
| MOD-08 | Supplier management | Logged in | 1) Create supplier 2) Add qualification 3) Link product | Supplier CRUD + qualifications + product links work | | |
| MOD-09 | Training management | Logged in | 1) Create course 2) Assign user 3) Record completion | Course/assignment/completion workflow works | | |
| MOD-10 | Billing & invoicing | Logged in | 1) Create invoice 2) Add items 3) Record payment 4) Download PDF | Invoice lifecycle works; PDF downloads | | |
| MOD-11 | Projects | Logged in | 1) Create project 2) Add sample 3) Remove sample | Project CRUD and sample linking work | | |
| MOD-12 | Workspace shortcuts | Logged in | 1) Add shortcut 2) Reorder 3) Delete | Shortcut tiles update correctly | | |
| MOD-13 | BI analytics | Logged in | 1) Open `/bi` 2) Create report 3) Run report | Report builder and execution work | | |

**Module 13 sign-off:** Tester: ______ Date: ______

---

## Module 14 — Integrations (SAP, SSO, API, Email)

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| INTG-01 | SAP sync page | Admin | 1) `/sap` 2) Save config 3) Push a type 4) Pull a type 5) Push-all / Pull-all | Config saved; sync runs; status page (`/sap/status`) reflects outcome | | |
| INTG-02 | SAP status view | Sync run | 1) `/sap/status` | Status shows last sync, counts, errors | | |
| INTG-03 | SSO config & test | Admin | 1) `/sso` 2) Save LDAP/OAuth/SAML config 3) Test connection | Config saved; test connection returns result | | |
| INTG-04 | API token create/revoke | Admin | 1) `/api-management/tokens` 2) Create token 3) Revoke token | Token created and displayed once; revoked token rejected | | |
| INTG-05 | API authentication | Token created | 1) Call `/api/samples` with Bearer token 2) Call without/invalid token | 200 with data for valid token; 401 otherwise | | |
| INTG-06 | Webhook create/toggle/logs | Admin | 1) Create webhook 2) Toggle active 3) Open logs | Webhook saved/toggled; delivery logged; failed jobs retried via worker | | |
| INTG-07 | Email config & test | Admin | 1) `/master/email-config` 2) Save SMTP 3) Set default 4) Send test | Config saved; test email delivered (or error surfaced) | | |

**Module 14 sign-off:** Tester: ______ Date: ______

---

## Module 15 — System, Backup & Deployment

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| SYS-01 | Backup creation | Admin | 1) `/backups` 2) Create backup | Backup file + `.meta.json` sidecar created in `storage/backups/` | | |
| SYS-02 | Backup download | Backup exists | 1) Download backup | File downloads; checksum matches `.meta.json` | | |
| SYS-03 | Backup restore | Backup exists | 1) Restore (type `RESTORE` to confirm) | Database restored from dump; note: destructive | | |
| SYS-04 | Backup settings | Admin | 1) Update retention & binary paths | Settings saved | | |
| SYS-05 | Deployment mode toggle | Admin | 1) `/deployment` 2) Save settings 3) Toggle cloud/local mode | Settings saved; mode toggled | | |
| SYS-06 | Queue worker | Jobs exist | 1) `php bin/worker.php --once` 2) `php bin/console queue:monitor` | Jobs processed; monitor lists pending/failed counts | | |
| SYS-07 | Multi-language switch | Logged in | 1) `/languages` 2) Switch language 3) Add/edit translation 4) Export | Language switches UI; translations managed & exported | | |
| SYS-08 | Plugin install/toggle | Admin | 1) `/plugins` 2) Install 3) Toggle 4) Uninstall | Plugin lifecycle works | | |
| SYS-09 | Installer builder | Admin | 1) `/installer/builder` 2) Build 3) Download 4) View log/history | Build completes; download works; log/history available | | |

**Module 15 sign-off:** Tester: ______ Date: ______

---

## Regression & Edge Cases

| TC ID | Test Case | Pre | Steps | Expected | Result | Notes |
|-------|-----------|-----|-------|----------|--------|-------|
| EDGE-01 | Pagination everywhere | >20 rows in any list | 1) Navigate large lists (billing, COA, deviations, ELN, stability, notifications, calibrations, inventory) | All paginate at 20 rows with prev/next + record count | | |
| EDGE-02 | Long/special characters | None | 1) Enter names/codes with unicode & special chars | Saved and displayed correctly (no truncation/encoding issues) | | |
| EDGE-03 | XSS input handling | None | 1) Enter `<script>alert(1)</script>` in text fields | Rendered as plain text; no script execution | | |
| EDGE-04 | Numeric validation | Result entry | 1) Enter non-numeric in numeric field | Rejected with validation error | | |
| EDGE-05 | Concurrent edits | Two sessions | 1) Edit same record from two sessions | Last-write-wins with no data corruption; audit trail shows both | | |
| EDGE-06 | Browser back after form submit | Logged in | 1) Submit form 2) Press browser back 3) Resubmit | No duplicate records (CSRF + redirect-after-post) | | |

## Summary

| Module | Total TCs | Pass | Fail | N/A | % Pass |
|--------|-----------|------|------|-----|--------|
| 1. Authentication & Access Control | | | | | |
| 2. Dashboard | | | | | |
| 3. Master Data | | | | | |
| 4. Batch Management | | | | | |
| 5. Samples & Analysis | | | | | |
| 6. Test Results & Approval | | | | | |
| 7. COA | | | | | |
| 8. Instrument Integration | | | | | |
| 9. OOS, CAPA & Deviations | | | | | |
| 10. Client Portal | | | | | |
| 11. Audit, Compliance & E-Sign | | | | | |
| 12. Users & Notifications | | | | | |
| 13. Additional Modules | | | | | |
| 14. Integrations | | | | | |
| 15. System, Backup & Deployment | | | | | |
| Edge Cases | | | | | |
| **TOTAL** | | | | | |

## Sign-off

| Role | Name | Signature | Date |
|------|------|-----------|------|
| Tested by (UAT Tester) | | | |
| Business Owner | | | |
| QA Lead | | | |
| Project Manager | | | |

**Overall UAT result:** APPROVED / APPROVED WITH DEFECTS / NOT APPROVED

**Defect summary & follow-up:** (list any open defects with TC ID, severity, owner, target fix date)
