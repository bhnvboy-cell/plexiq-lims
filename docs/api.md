# PlexiQ LIMS REST API Reference

## Overview

PlexiQ LIMS provides a RESTful JSON API for integration with external systems (ERP, instruments, custom applications). All API requests require token-based authentication.

**Base URL:** `http://your-server/api/`  
**Content-Type:** `application/json`  
**Authentication:** Bearer token via `Authorization` header

---

## Authentication

### Obtain API Token

API tokens are managed in the UI under Administration > API Tokens. Each token is associated with a specific user and inherits their role-based permissions.

```
Authorization: Bearer <your-api-token>
X-CSRF-TOKEN: <csrf-token>  (for state-changing requests)
```

### Error Responses

| Status Code | Meaning |
|------------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized (missing/invalid token) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Rate Limit Exceeded |
| 500 | Server Error |

---

## Samples

### List Samples

```
GET /api/samples
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | Page number (default: 1) |
| `per_page` | int | Items per page (default: 20) |
| `status` | string | Filter by status |
| `customer_id` | int | Filter by customer |
| `product_id` | int | Filter by product |
| `priority` | string | Filter by priority (Low/Normal/High/Urgent) |
| `search` | string | Search by sample code, batch number |

**Response:**
```json
{
    "items": [
        {
            "id": 1,
            "sample_code": "SMP-20260627-00001",
            "status": "Registered",
            "priority": "Normal",
            "customer_name": "Acme Corp",
            "product_name": "Glucose Syrup",
            "batch_number": "BATCH-001",
            "created_at": "2026-06-27T10:00:00"
        }
    ],
    "total": 1,
    "per_page": 20,
    "current_page": 1,
    "last_page": 1
}
```

### Get Single Sample

```
GET /api/samples/{id}
```

**Response:**
```json
{
    "id": 1,
    "sample_code": "SMP-20260627-00001",
    "customer_id": 1,
    "product_id": 1,
    "batch_number": "BATCH-001",
    "batch_size": "1000 kg",
    "status": "In Progress",
    "priority": "Normal",
    "manufacture_date": "2026-06-25",
    "expiry_date": "2026-12-25",
    "received_date": "2026-06-27",
    "target_completion_date": "2026-07-04",
    "assigned_analyst_id": 2,
    "assigned_reviewer_id": 3,
    "assigned_approver_id": 4,
    "customer_name": "Acme Corp",
    "product_name": "Glucose Syrup",
    "analyst_name": "John Analyst",
    "reviewer_name": "Jane Reviewer",
    "approver_name": "Bob Approver",
    "registered_by_name": "Admin User",
    "notes": "Rush order",
    "tests": [
        {
            "id": 1,
            "test_name": "Dextrose Equivalent",
            "method_name": "ICUMSA GS4/1",
            "status": "Pending",
            "assigned_to": "John Analyst"
        }
    ],
    "created_at": "2026-06-27T10:00:00",
    "updated_at": "2026-06-27T10:30:00"
}
```

### Create Sample

```
POST /api/samples
```

**Request Body:**
```json
{
    "customer_id": 1,
    "product_id": 1,
    "batch_number": "BATCH-002",
    "batch_size": "500 kg",
    "manufacture_date": "2026-06-26",
    "expiry_date": "2026-12-26",
    "received_date": "2026-06-27",
    "target_completion_date": "2026-07-04",
    "priority": "High",
    "assigned_analyst_id": 2,
    "assigned_reviewer_id": 3,
    "assigned_approver_id": 4,
    "notes": "Priority testing requested",
    "test_ids": [1, 3, 5]
}
```

**Response (201 Created):**
```json
{
    "id": 2,
    "sample_code": "SMP-20260627-00002",
    "status": "Registered",
    "message": "Sample created successfully"
}
```

### Update Sample

```
PUT /api/samples/{id}
```

**Request Body:** (same fields as create, all optional)

**Response:**
```json
{
    "id": 2,
    "message": "Sample updated successfully"
}
```

### Update Sample Status

```
PUT /api/samples/{id}/status
```

**Request Body:**
```json
{
    "status": "In Progress"
}
```

**Allowed Transitions:**

| Current Status | Next Statuses |
|---------------|--------------|
| Registered | In Progress |
| In Progress | Reviewed, Rejected |
| Reviewed | Approved, Rejected |
| Approved | COA Released |

**Response:**
```json
{
    "id": 2,
    "status": "In Progress",
    "message": "Sample status changed to In Progress"
}
```

---

## Tests & Results

### List Tests for a Sample

```
GET /api/samples/{id}/tests
```

### Submit Result

```
POST /api/results
```

**Request Body:**
```json
{
    "sample_test_id": 1,
    "result_value": "95.5",
    "unit": "%",
    "notes": "Result verified"
}
```

### List Pending Results

```
GET /api/results/pending
```

---

## Batches

### List Batches

```
GET /api/batches
```

### Get Batch Details

```
GET /api/batches/{id}
```

### Create Batch

```
POST /api/batches
```

---

## Certificate of Analysis (COA)

### Generate COA

```
POST /api/coa/generate/{sample_id}
```

### List COAs

```
GET /api/coa
```

---

## Instruments

### List Instruments

```
GET /api/instruments
```

### Import Instrument Results

```
POST /api/instruments/results
```

**Request Body:**
```json
{
    "instrument_id": 1,
    "data": [
        {"sample_barcode": "SMP-001", "test_name": "pH", "value": "7.2"},
        {"sample_barcode": "SMP-001", "test_name": "Viscosity", "value": "450"}
    ]
}
```

---

## Customers & Products

### List Customers

```
GET /api/customers
```

### List Products

```
GET /api/products
```

### Create Product-Test Mapping

```
POST /api/product-tests
```

---

## Barcode Lookup

### Lookup by Barcode

```
GET /api/barcode/{code}
```

**Response:**
```json
{
    "type": "sample",
    "id": 1,
    "sample_code": "SMP-20260627-00001",
    "status": "Registered",
    "product_name": "Glucose Syrup",
    "url": "/samples/1"
}
```

---

## SAP Integration

### Sync Samples to SAP

```
POST /api/sap/sync/samples
```

### Check Sync Status

```
GET /api/sap/status
```

---

## Notifications

### List Unread Notifications

```
GET /api/notifications/unread
```

### Mark Notification as Read

```
PUT /api/notifications/{id}/read
```

---

## Webhooks

### List Webhooks

```
GET /api/webhooks
```

### Create Webhook

```
POST /api/webhooks
```

**Request Body:**
```json
{
    "name": "ERP Sync",
    "url": "https://erp.example.com/webhook",
    "events": ["sample.created", "sample.status_changed", "result.submitted"],
    "is_active": true
}
```

---

## Rate Limiting

API requests are rate-limited per token. Limits are configurable in the database.

Current limits (default):
- 60 requests per minute
- 1000 requests per hour

Rate limit headers are included in all responses:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1687890000
```

---

## Error Codes

| Code | Description |
|------|-------------|
| `invalid_token` | API token is missing or invalid |
| `expired_token` | API token has expired |
| `insufficient_permissions` | Token lacks required role |
| `validation_error` | Request body failed validation |
| `not_found` | Requested resource does not exist |
| `invalid_transition` | Status change not allowed |
| `rate_limited` | Too many requests |

---

## SDK / Client Libraries

Currently available:
- **cURL** / any HTTP client
- **PowerShell** scripts in `bin/api/`

Example cURL usage:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     https://your-server/api/samples
```

Example PowerShell usage:

```powershell
$headers = @{
    "Authorization" = "Bearer YOUR_TOKEN"
    "Content-Type" = "application/json"
}
Invoke-RestMethod -Uri "http://localhost:8080/api/samples" -Headers $headers
```
