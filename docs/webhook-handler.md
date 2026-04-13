# Webhook Handler — Technical Documentation

## Overview

The webhook handler receives external issue events (created, updated, note added) and synchronizes them as phpKanMaster tasks. It uses an IP allowlist for authentication, configurable status mapping, and tiered priority mapping.

**Source code**: `app/Http/Controllers/WebhookController.php`, `app/Services/WebhookService.php`

---

## Architecture

```
External System → POST /webhooks/{source}
                       │
                       ▼
              WebhookController
              ├── Validate source format
              ├── Check IP allowlist
              ├── Validate payload
              └── Delegate to WebhookService
                       │
                       ▼
                 WebhookService
              ├── Route by event_type
              ├── Map fields (status, priority, etc.)
              ├── Create/update tasks via PostgREST
              ├── Sync notes
              └── Handle conflicts
```

Tasks and notes are created/updated through PostgREST (not Eloquent). The `issue_mappings` and `webhook_status_mappings` tables are accessed through Eloquent models.

---

## Endpoint

```
POST /webhooks/{source}
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `source` | URL segment | Source system name (alphanumeric + underscore). E.g., `redmine`, `jira` |

**Authentication**: IP allowlist via `WEBHOOK_ALLOWED_IPS` env var. No auth middleware — the route is public, relying on its own IP check.

**Rate limiting**: 60 requests/minute (throttle middleware).

---

## Payload Format

```json
{
  "event_type": "issue.created|issue.updated|issue.note_added",
  "issue": {
    "id": "12345",
    "summary": "Bug in login page",
    "description": "<p>Steps and details</p>",
    "status": 20,
    "priority": 40,
    "severity": 2,
    "reporter": { "realname": "John Smith", "username": "jsmith" },
    "handler": { "realname": "Mary Johnson", "username": "mjohnson" },
    "project": { "id": 5, "name": "Customer Portal" },
    "category": { "name": "UI Bug" },
    "tags": ["mobile", "urgent"],
    "steps_to_reproduce": "1. Open site\n2. Tap Login",
    "additional_information": "Browser: Safari 17.3",
    "notes": [
      {
        "id": 99,
        "author": { "realname": "Jane Doe", "username": "jdoe" },
        "text": "Still reproducible",
        "created_at": "2026-04-11T09:15:00Z"
      }
    ],
    "created_at": "2026-04-10T12:00:00Z",
    "updated_at": "2026-04-12T10:00:00Z"
  }
}
```

### Required fields

| Field | Required | Notes |
|-------|----------|-------|
| `event_type` | Yes | One of `issue.created`, `issue.updated`, `issue.note_added` |
| `issue.id` | Yes | External system's unique identifier for the issue |

All other fields are optional and handled gracefully when absent.

---

## Event Handling

### `issue.created`

1. Check idempotency — if `issue_mappings` already has an entry for `(source, external_id)`, skip and return success.
2. Map fields: `summary` → `title`, `description` → `description`, `status` → column mapping, `priority` → tiered mapping.
3. Append additional fields (reporter, handler, project, category, tags, severity, steps, extra) to description in readable format.
4. Create task via PostgREST.
5. Create `issue_mappings` record linking `external_id` → `task_id`.

**Response**:
```json
{ "success": true, "task_id": "uuid", "skipped": false }
```

### `issue.updated`

1. Look up `issue_mappings` by `(source, external_id)`. Return 404 if not found.
2. Sync all notes from payload to `task_notes` (skip duplicates by external note ID).
3. Build update data from mapped fields.
4. **Conflict detection**: If the local task was modified after `last_synced_at` but before the external `updated_at`, a conflict exists.
   - On conflict: write a conflict note to `task_notes` instead of overwriting task fields.
   - No conflict: update task via PostgREST.
5. Update `last_synced_at` on the mapping record.

**Response**:
```json
{ "success": true, "task_id": "uuid", "has_conflict": false }
```

### `issue.note_added`

1. Look up `issue_mappings` by `(source, external_id)`. Return 404 if not found.
2. Only the **first** note in the `notes` array is processed (single new note).
3. Check idempotency — skip if a note with this external ID already exists on the task.
4. Append formatted note to `task_notes`.

**Response**:
```json
{ "success": true, "task_id": "uuid", "skipped": false }
```

---

## Priority Mapping

External priority IDs are mapped to phpKanMaster's three-tier system using numeric thresholds:

| External ID | External Name | phpKanMaster Priority |
|-------------|---------------|----------------------|
| 10 | None | `low` |
| 20 | Low | `low` |
| 30 | Normal | `medium` |
| 40 | High | `high` |
| 50 | Urgent | `high` |
| 60 | Immediate | `high` |

**Logic** (in `WebhookService::mapPriority`):
- `>= 40` → `high`
- `>= 30` → `medium`
- `< 30` → `low`

This is hardcoded, not configurable via database. To change the mapping, modify `mapPriority()` in `WebhookService.php`.

---

## Status Mapping

Status mapping is configurable per source through the `webhook_status_mappings` database table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `source` | VARCHAR(50) | Source system name (e.g., `redmine`) |
| `external_status` | INTEGER | External status code |
| `kanban_column` | VARCHAR(20) | phpKanMaster column: `new`, `in_progress`, `review`, `on_hold`, `done` |

**Unique constraint**: `(source, external_status)`

When no mapping is found for a given `(source, external_status)`, the system defaults to `new` and logs a warning.

### Setting up status mappings

Insert rows into the `webhook_status_mappings` table. Example for a source called `redmine`:

```sql
INSERT INTO webhook_status_mappings (id, source, external_status, kanban_column)
VALUES
  (gen_random_uuid(), 'redmine', 10, 'new'),
  (gen_random_uuid(), 'redmine', 20, 'in_progress'),
  (gen_random_uuid(), 'redmine', 30, 'on_hold'),
  (gen_random_uuid(), 'redmine', 40, 'review'),
  (gen_random_uuid(), 'redmine', 50, 'done');
```

---

## Database Tables

### `issue_mappings`

Tracks the relationship between external issues and phpKanMaster tasks.

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID (PK) | Primary key |
| `external_id` | VARCHAR(255) | External system's issue ID |
| `task_id` | UUID (FK → tasks.id) | phpKanMaster task ID |
| `source` | VARCHAR(50) | Source system name |
| `project_id` | INTEGER (nullable) | External project ID |
| `last_synced_at` | TIMESTAMPTZ (nullable) | Last successful sync timestamp |
| `created_at` | TIMESTAMPTZ | Record creation time |
| `updated_at` | TIMESTAMPTZ | Record update time |

**Unique constraint**: `(source, external_id)`
**Foreign key**: `task_id` → `tasks.id` (CASCADE delete)

### `webhook_status_mappings`

See [Status Mapping](#status-mapping) above.

---

## Conflict Resolution

When `issue.updated` arrives but the local task has been modified since the last sync, the handler creates a conflict note instead of overwriting the task.

**Detection logic** (`WebhookService::detectConflict`):
```
conflict = (task.updated_at > mapping.last_synced_at) AND (task.updated_at < issue.updated_at)
```

**Conflict note format**:
```
--- Sync Conflict Detected on 2026-04-12T14:22:00Z ---
Local task was modified after last sync (2026-04-12T10:00:00Z).
External changes:

Field: status
  External: 50 (done)
  Local was: in_progress

Field: handler
  External: Mary Johnson (mjohnson)
  Local was: jsmith

--- End Sync Conflict ---
```

---

## IP Allowlist

Configured via `WEBHOOK_ALLOWED_IPS` environment variable.

| Format | Example |
|--------|---------|
| Single IP | `192.168.1.100` |
| CIDR range | `10.0.0.0/8` |
| Multiple | `192.168.1.100,10.0.0.0/8,203.0.113.50` |

**Behavior**:
- Empty/missing → all IPs allowed (backward compatible, not recommended for production)
- Non-matching IP → HTTP 403 with logged warning
- CIDR matching uses bitwise mask comparison (IPv4 only)

---

## Additional Fields Format

Fields not directly mapped to task columns are appended to the description:

```
--- Additional Fields ---
Reporter: John Smith (jsmith)
Handler: Mary Johnson (mjohnson)
Project: Customer Portal
Category: UI Bug
Tags: mobile, urgent
Severity: 2
Steps to Reproduce:
1. Open site on iPhone
2. Navigate to homepage

Additional Information:
Browser: Safari 17.3, iOS 17.4

--- Additional Data ---
{"custom_field_1": "value", "custom_field_2": 42}
```

The `--- Additional Data ---` section contains any fields not in the explicit handling list, serialized as JSON.

---

## Note Synchronization

Notes are stored in the existing `task_notes` table. External note IDs are embedded in the note content for deduplication:

```
--- Note from John Smith (jsmith) on 2026-04-11T09:15:00Z ---
Still reproducible on iOS 17.4 with Safari (external_id: 99)
```

The `(external_id: XXX)` suffix is used by `noteExists()` to detect duplicates by querying all notes for a task and pattern-matching.

---

## Response Codes

| HTTP Code | Condition |
|-----------|-----------|
| 200 | Success (with `task_id`, `skipped`, `has_conflict` flags) |
| 400 | Invalid JSON payload or validation failure |
| 403 | IP not in allowlist |
| 404 | Issue mapping not found (for update/note_added events) |
| 422 | Missing `event_type` or `issue.id`, unknown event type, invalid source format |
| 500 | Database or PostgREST error |

---

## Environment Variables

| Variable | Purpose | Default |
|----------|---------|---------|
| `WEBHOOK_ALLOWED_IPS` | Comma-separated IP/CIDR allowlist | `""` (allow all) |
| `JWT_SECRET` | Bearer token for PostgREST API calls | (required) |
| `PGRST_BASE_URL` | PostgREST URL for browser (used by frontend) | `/api` |

Internal PostgREST calls use `http://postgrest:3000` (hardcoded in `WebhookService`).

---

## File Reference

| File | Purpose |
|------|---------|
| `app/Http/Controllers/WebhookController.php` | Request validation, IP allowlist, routing |
| `app/Services/WebhookService.php` | Business logic: event handling, field mapping, conflict detection |
| `app/Models/IssueMapping.php` | Eloquent model for `issue_mappings` table |
| `app/Models/WebhookStatusMapping.php` | Eloquent model for `webhook_status_mappings` table |
| `database/migrations/2026_04_12_214313_create_issue_mappings_table.php` | Schema for `issue_mappings` |
| `database/migrations/2026_04_12_214314_create_webhook_status_mappings_table.php` | Schema for `webhook_status_mappings` |
| `routes/web.php` | Route: `POST /webhooks/{source}` |
| `config/app.php` | Config: `jwt_secret`, `webhook_allowed_ips` |