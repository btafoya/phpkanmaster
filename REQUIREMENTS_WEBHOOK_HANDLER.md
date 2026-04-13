# Requirements Specification: External Issue Webhook Handler

## Overview
REST webhook handler that receives external issue webhook events and creates/updates phpKanMaster tasks. Designed for single source now, architect for multiple sources in future.

---

## 1. Functional Requirements

### 1.1 Supported Event Types
| Event Type | Payload Notes Included |
|------------|------------------------|
| `issue.created` | No notes (empty array) |
| `issue.updated` | All notes currently on the issue |
| `issue.note_added` | Only the single newly added note |

### 1.2 Database Schema Changes

#### Table: `issue_mappings`
Tracks relationship between external issue IDs and phpKanMaster tasks.

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `external_id` | VARCHAR(255) | External system's issue ID |
| `task_id` | UUID | FK to `tasks.id` |
| `source` | VARCHAR(50) | Source system name (e.g., "jira", "redmine") |
| `project_id` | INTEGER | External project ID (for multi-project sources) |
| `last_synced_at` | TIMESTAMPTZ | Last successful sync timestamp |
| `created_at` | TIMESTAMPTZ | Record creation time |
| `updated_at` | TIMESTAMPTZ | Record update time |

**Unique constraint**: `(source, external_id)`

#### Table: `webhook_status_mappings`
Configurable mapping from external status codes to kanban columns.

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `source` | VARCHAR(50) | Source system name |
| `external_status` | INTEGER | External status code (e.g., 50, 20) |
| `kanban_column` | VARCHAR(20) | phpKanMaster column: `new`, `in_progress`, `review`, `on_hold`, `done` |
| `created_at` | TIMESTAMPTZ | Record creation time |

**Unique constraint**: `(source, external_status)`

### 1.3 Field Mapping

| External Issue Field | phpKanMaster Task Field |
|---------------------|------------------------|
| `summary` | `title` |
| `description` | `description` (rich text) |
| `status` | → `webhook_status_mappings` → `task_column` |
| `priority` | `priority` (low/medium/high) |
| `severity` | Appended to description |
| `reporter` | Appended to description |
| `handler` | Appended to description |
| `project` | Appended to description |
| `category` | Appended to description |
| `tags` | Appended to description |
| `steps_to_reproduce` | Appended to description |
| `additional_information` | Appended to description |
| All other fields | Appended to description as user-friendly JSON |

**Description Append Format**:
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
3. Tap Login button

Additional Information:
Browser: Safari 17.3, iOS 17.4
```

### 1.4 Notes Synchronization

**Table**: `task_notes` (already exists)
| Column | Type |
|--------|------|
| `id` | UUID (PK) |
| `task_id` | UUID (FK) |
| `content` | TEXT |
| `created_at` | TIMESTAMPTZ |
| `updated_at` | TIMESTAMPTZ |

**Sync Logic**:
- `issue.created`: No notes sync
- `issue.updated`: Sync ALL notes from payload to `task_notes` (append new notes by external note ID)
- `issue.note_added`: Sync ONLY the single new note

**Note Content Format**:
```
--- Note from John Smith (jsmith) on 2026-04-11T09:15:00Z ---
Still reproducible on iOS 17.4 with Safari
```

### 1.5 Conflict Resolution

When `issue.updated` arrives but the local task has been modified since last sync:

→ **Write changes to a `task_note`** in user-friendly rich text format instead of overwriting task.

```
--- Sync Conflict Detected on 2026-04-12T14:22:00Z ---
Local task was modified after last sync (2026-04-12T10:00:00Z).
External changes:

Field: status
  External: 50 (Done)
  Local was: 20 (In Progress)

Field: handler
  External: Mary Johnson (mjohnson)
  Local was: John Smith (jsmith)

--- End Sync Conflict ---
```

### 1.6 Webhook Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/webhooks/{source}` | Receive webhooks from external system |

**Example**: `/webhooks/jira`, `/webhooks/redmine`

**Behavior**: Route to appropriate handler based on `source` URL parameter.

---

## 2. Authentication

### IP Allowlist
- Configuration: Single env var `WEBHOOK_ALLOWED_IPS`
- Format: Comma-separated IP addresses or CIDR ranges
- Example: `WEBHOOK_ALLOWED_IPS=192.168.1.100,10.0.0.0/8,203.0.113.50`

### Behavior
- If request IP not in allowlist → HTTP 403 Forbidden
- Log all rejected requests for audit

---

## 3. Non-Functional Requirements

### 3.1 Error Handling
- Invalid JSON → HTTP 400 Bad Request
- Unknown event_type → HTTP 422 Unprocessable Entity (log but don't fail)
- Unknown source → HTTP 422 Unprocessable Entity
- Database errors → HTTP 500 Internal Server Error (retry logic out of scope)
- Missing required fields → HTTP 422 with field-level error details

### 3.2 Logging
- Log all incoming webhook requests (source, event_type, external_id)
- Log all sync operations (task_id, operation)
- Log all rejections (reason)

### 3.3 Idempotency
- `issue.created`: Skip if `issue_mappings` entry already exists for (source, external_id)
- `issue.updated`: Always process (conflict resolution handles divergence)
- `issue.note_added`: Skip if note with same external note ID already exists

---

## 4. Acceptance Criteria

### AC1: Issue Created
- [ ] Webhook with `issue.created` creates new task in `tasks` table
- [ ] `issue_mappings` entry created linking external_id to task_id
- [ ] Title, description, status (mapped), priority set correctly
- [ ] Additional fields appended to description in readable format
- [ ] No notes created (notes array is empty for this event)

### AC2: Issue Updated
- [ ] Existing task updated based on `issue_mappings` lookup
- [ ] All notes from payload appended to `task_notes`
- [ ] Conflict detection: if local modified since last sync, create conflict note instead of overwriting
- [ ] `last_synced_at` updated in `issue_mappings`

### AC3: Issue Note Added
- [ ] Only the single new note appended to `task_notes`
- [ ] Duplicate notes (same external note ID) ignored

### AC4: IP Allowlist
- [ ] Requests from allowed IPs processed normally
- [ ] Requests from disallowed IPs return 403

### AC5: Status Mapping
- [ ] External status codes map to kanban columns via `webhook_status_mappings`
- [ ] Unmapped status codes handled gracefully (log warning, default to `new`)

### AC6: Multi-Source Architecture
- [ ] Same endpoint structure supports multiple sources
- [ ] Each source has its own status mappings
- [ ] Future sources can be added without code changes

---

## 5. Open Questions

- [x] Issue ID tracking strategy → Separate `issue_mappings` table
- [x] Status mapping → Configurable mapping table
- [x] Authentication → IP allowlist
- [x] Field mapping → summary→title, description→description, rest to description + JSON
- [x] Conflict resolution → Note the conflict instead of overwriting
- [x] Notes handling → Existing `task_notes` table
- [x] IP config storage → Single env var
- [x] Webhook URL structure → Separate endpoint per source (`/webhooks/{source}`)
- [x] Number of sources → One now, architect for many
- [x] Mapping table structure → source + external_status → kanban_column

---

## 6. Out of Scope (for this phase)

- Retry logic for failed syncs
- Webhook signature verification (HMAC)
- Admin UI for managing mappings
- Webhook delivery verification (external system callback)
- Partial sync (selective field updates)
