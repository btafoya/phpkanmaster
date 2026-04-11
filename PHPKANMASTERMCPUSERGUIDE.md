# phpKanMaster Agent MCP Server — User Guide

phpKanMaster includes a custom MCP (Model Context Protocol) server implementation that allows external agents (like Openclaw) to authenticate and query task data via a JWT-based API.

---

## Overview

The Agent MCP Server consists of:

| Component | Purpose |
|-----------|---------|
| `POST /api/agent/token` | Issues short-lived JWT tokens for agent authentication |
| `POST /api/agent/tasks` | Creates new tasks |
| `PATCH /api/agent/tasks/{id}` | Updates existing tasks |
| `DELETE /api/agent/tasks/{id}` | Deletes tasks |
| `GET /api/active_tasks_with_notes` | Query active tasks with notes (read-only) |
| `agent` PostgreSQL role | Role with SELECT on view, INSERT/UPDATE/DELETE on tasks |

---

## Architecture

```
Openclaw Agent
    │
    │  POST /api/agent/token (username + password)
    ▼
Laravel (issues JWT with role=agent, exp=1h)
    │
    ├── GET /api/active_tasks_with_notes
    │       │
    │       │  Bearer JWT
    │       ▼
    │   PostgREST (validates JWT → switches to `agent` role)
    │       │
    │       ▼
    │   PostgreSQL (SELECT on active_tasks_with_notes view)
    │
    ├── POST /api/agent/tasks
    ├── PATCH /api/agent/tasks/{id}
    └── DELETE /api/agent/tasks/{id}
            │
            │  Bearer JWT
            ▼
        PostgREST (INSERT/UPDATE/DELETE on tasks via agent role)
            │
            ▼
        PostgreSQL (write on tasks table)
```

---

## Getting Started

### 1. Configure Environment

Add to your `.env` file:

```env
JWT_SECRET=your_secret_at_least_32_characters
```

### 2. Start the Stack

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
```

### 3. Obtain a Token

```bash
curl -s -X POST http://localhost:8181/api/agent/token \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"your_password"}'
```

Response:

```json
{
  "token": "eyJ...",
  "expires_in": 3600
}
```

---

## Querying Tasks

### Basic Query

```bash
curl -s http://localhost:8181/api/active_tasks_with_notes \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" | jq .
```

### Select Specific Columns

```bash
curl -s "http://localhost:8181/api/active_tasks_with_notes?select=id,title,task_column,notes,priority&order=position.asc" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" | jq .
```

### Available Columns

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Unique task identifier |
| `title` | text | Task title |
| `description` | text | Full task description |
| `due_date` | timestamp | Due date (nullable) |
| `priority` | text | low / medium / high |
| `task_column` | text | new / in_progress / review / on_hold |
| `position` | integer | Sort order within column |
| `category_id` | uuid | Category reference (nullable) |
| `parent_id` | uuid | Parent task for subtasks (nullable) |
| `reminder_at` | timestamp | Reminder time (nullable) |
| `disable_notifications` | boolean | Whether notifications are silenced |
| `notes` | json | Array of note objects |
| `created_at` | timestamp | Creation time |
| `updated_at` | timestamp | Last update time |

### Notes Structure

Each task includes a `notes` array:

```json
"notes": [
  {
    "id": "uuid",
    "content": "Note text content",
    "created_at": "2026-04-09T...",
    "updated_at": "2026-04-09T..."
  }
]
```

---

## Task Schema Reference

This section describes all task concepts in detail for agents consuming the API.

---

### Task Columns (Kanban States)

Tasks flow through these columns:

| Column | Description |
|--------|-------------|
| `new` | Newly created, not started |
| `in_progress` | Currently being worked on |
| `review` | Completed but under review |
| `on_hold` | Temporarily paused |
| `done` | Completed (excluded from `active_tasks_with_notes` view) |

**Note:** When querying `active_tasks_with_notes`, tasks in `done` column are **excluded**.

---

### Priority Levels

| Priority | Description | Use Case |
|----------|-------------|----------|
| `low` | Background tasks | Nice to have, no urgency |
| `medium` | Default priority | Normal work items |
| `high` | Urgent tasks | Requires immediate attention |

Priority affects:
- Visual sorting/display in the kanban board
- Notification ordering (high priority first)
- Pushover pushover priority field

---

### Parent-Child Tasks (Subtasks)

Tasks can have a `parent_id` to create a **subtask hierarchy**:

```json
{
  "id": "parent-uuid",
  "title": "Parent Task",
  "task_column": "in_progress",
  "subtasks": []
}
```

**Creating a subtask:**

```bash
curl -X POST http://localhost:8181/api/agent/tasks \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Subtask","parent_id":"parent-uuid","task_column":"new"}'
```

**Finding subtasks:**

```bash
# Filter by parent_id
curl -s "http://localhost:8181/api/active_tasks_with_notes?parent_id=eq.parent-uuid" \
  -H "Authorization: Bearer $TOKEN"
```

**Subtask behavior:**
- Subtasks inherit no state from parent (column, priority are independent)
- When parent moves to `done`, subtasks remain active unless also moved
- Deleting a parent does **not** cascade delete subtasks
- Subtasks can have their own subtasks (nested to any depth)

---

### Categories

Categories group tasks by topic (e.g., "Work", "Personal", "Shopping").

| Field | Type | Description |
|-------|------|-------------|
| `id` | uuid | Unique category identifier |
| `name` | text | Display name |
| `color` | text | Hex color code (e.g., `#FF5733`) |

**Assigning a category:**

```bash
curl -X PATCH http://localhost:8181/api/agent/tasks/{id} \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"category_id":"category-uuid"}'
```

**Filtering by category:**

```bash
curl -s "http://localhost:8181/api/active_tasks_with_notes?category_id=eq.category-uuid" \
  -H "Authorization: Bearer $TOKEN"
```

---

### Due Dates and Reminders

| Field | Type | Description |
|-------|------|-------------|
| `due_date` | date | When the task is due (YYYY-MM-DD) |
| `reminder_at` | timestamp | When to send reminder notification |

**Setting due date and reminder:**

```bash
curl -X POST http://localhost:8181/api/agent/tasks \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Meeting prep",
    "due_date": "2026-04-15",
    "reminder_at": "2026-04-14T09:00:00Z"
  }'
```

**Reminder behavior:**
- If `reminder_at` is set, a notification fires at that time
- The `reminder_sent` field tracks whether notification was sent
- Reminders respect `disable_notifications` flag

---

### Notifications

#### Disable Notifications

| Field | Type | Description |
|-------|------|-------------|
| `disable_notifications` | boolean | If true, no reminders or push notifications |

```bash
# Disable notifications for a task
curl -X PATCH http://localhost:8181/api/agent/tasks/{id} \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"disable_notifications": true}'
```

#### Pushover Settings

When enabled in `.env`, tasks can use Pushover for urgent notifications:

| Field | Type | Description |
|-------|------|-------------|
| `pushover_priority` | integer | Pushover urgency: -2, -1, 0, 1, 2 |
| `pushover_retry` | integer | Retry interval in seconds (for priority 2) |
| `pushover_expire` | integer | Expire time in seconds (for priority 2) |

| Priority Value | Meaning |
|----------------|---------|
| `-2` | No notification (quiet) |
| `-1` | Normal notification, no sound |
| `0` | Normal notification |
| `1` | High priority, bypass quiet hours |
| `2` | Emergency - repeats until acknowledged |

**Pushover configuration in `.env`:**
```env
PUSHOVER_TOKEN=your_pushover_token
PUSHOVER_USER_KEY=your_user_key
NOTIFY_PUSHOVER=true
```

---

### Notes

Tasks can have multiple notes (rich text content):

| Field | Type | Description |
|-------|------|-------------|
| `id` | uuid | Unique note identifier |
| `content` | text | Note content (HTML from Summernote editor) |
| `created_at` | timestamp | Creation time |
| `updated_at` | timestamp | Last modification time |

**Notes are returned in the task response as a JSON array:**

```json
{
  "id": "task-uuid",
  "title": "Task with notes",
  "notes": [
    {
      "id": "note-uuid",
      "content": "<p>Note content here</p>",
      "created_at": "2026-04-11T10:00:00Z",
      "updated_at": "2026-04-11T10:00:00Z"
    }
  ]
}
```

**Note: Currently, agents can create tasks with notes, but direct note creation via the agent API is limited to INSERT permission on `task_notes` table.**

---

### Complete Task Response Example

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "title": "Complete project proposal",
  "description": "Draft and finalize the Q2 project proposal for stakeholder review",
  "due_date": "2026-04-20",
  "priority": "high",
  "task_column": "in_progress",
  "position": 3,
  "category_id": "660e8400-e29b-41d4-a716-446655440001",
  "parent_id": null,
  "reminder_at": "2026-04-19T09:00:00Z",
  "disable_notifications": false,
  "created_at": "2026-04-10T14:30:00Z",
  "updated_at": "2026-04-11T08:15:00Z",
  "notes": [
    {
      "id": "770e8400-e29b-41d4-a716-446655440002",
      "content": "<p>Remember to include ROI calculations</p>",
      "created_at": "2026-04-10T15:00:00Z",
      "updated_at": "2026-04-10T15:00:00Z"
    }
  ]
}
```

---

## Authentication Flow

### JWT Token Contents

The server issues JWTs with this payload:

```json
{
  "iss": "http://localhost:8181",
  "sub": "admin",
  "role": "agent",
  "iat": 1234567890,
  "nbf": 1234567890,
  "exp": 1234571490
}
```

- Token expires in 3600 seconds (1 hour)
- `role: agent` claim switches PostgREST to the restricted PostgreSQL role
- Only the `active_tasks_with_notes` view is accessible

### Rate Limiting

| Endpoint | Limit |
|----------|-------|
| `POST /api/agent/token` | 5 requests/minute |
| `POST /api/agent/tasks` | 30 requests/minute |
| `PATCH /api/agent/tasks/{id}` | 30 requests/minute |
| `DELETE /api/agent/tasks/{id}` | 30 requests/minute |

---

## Filtering Tasks

### By Column

```bash
curl -s "http://localhost:8181/api/active_tasks_with_notes?task_column=eq.in_progress" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### By Priority

```bash
curl -s "http://localhost:8181/api/active_tasks_with_notes?priority=eq.high" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### By Category

```bash
curl -s "http://localhost:8181/api/active_tasks_with_notes?category_id=eq.uuid-here" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Combined Filters

```bash
curl -s "http://localhost:8181/api/active_tasks_with_notes?task_column=eq.in_progress&priority=eq.high&order=due_date.asc" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Production Deployment

### Generate a Strong JWT Secret

```bash
docker compose exec app php -r "echo bin2hex(random_bytes(32));"
```

### Security Notes

- The `agent` PostgreSQL role has **SELECT** on `active_tasks_with_notes` view and **INSERT/UPDATE/DELETE** on `tasks` table
- JWT tokens expire after 1 hour — implement refresh logic in your agent
- The rate limit (5/min on token, 30/min on tasks) helps prevent abuse
- Consider using HTTPS in production to protect token transmission

---

## Troubleshooting

### "Invalid credentials" Error

Verify your `APP_USER` and `APP_PASSWORD_HASH` in `.env`:

```bash
docker compose exec app php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
```

### "Token signature invalid"

Ensure `JWT_SECRET` in `.env` matches `PGRST_JWT_SECRET` in `docker-compose.yml`:

```bash
docker compose up -d --force-recreate postgrest
```

### Empty Results

The view only shows **active tasks** (task_column != 'done'). Tasks in the "done" column are not included.

### "Failed to create/update task"

Ensure migrations have run and the `agent` role has the necessary permissions:

```bash
docker compose exec app php artisan migrate
```

---

## Creating Tasks

```bash
curl -X POST http://localhost:8181/api/agent/tasks \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"New Task","priority":"high","task_column":"new"}'
```

**Request body fields:**

| Field | Type | Required | Default |
|-------|------|----------|---------|
| `title` | string | Yes | - |
| `description` | string | No | null |
| `priority` | string | No | medium |
| `category_id` | uuid | No | null |
| `due_date` | date | No | null |
| `task_column` | string | No | new |
| `position` | integer | No | 0 |
| `reminder_at` | timestamp | No | null |
| `parent_id` | uuid | No | null |

**Response:** Returns created task with 201 status.

## Editing Tasks

```bash
curl -X PATCH http://localhost:8181/api/agent/tasks/{id} \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Updated Title","priority":"high"}'
```

All fields are optional for partial updates. Only provided fields are updated.

**Response:** Returns updated task with 200 status.

## Deleting Tasks

```bash
curl -X DELETE http://localhost:8181/api/agent/tasks/{id} \
  -H "Authorization: Bearer $TOKEN"
```

**Response:** Returns `{"deleted": true}` with 200 status.

## Quick Reference

```bash
# Get token
TOKEN=$(curl -s -X POST http://localhost:8181/api/agent/token \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"your_password"}' | jq -r '.token')

# Query all active tasks
curl -s http://localhost:8181/api/active_tasks_with_notes \
  -H "Authorization: Bearer $TOKEN" | jq .

# Query with filters
curl -s "http://localhost:8181/api/active_tasks_with_notes?select=id,title,priority&task_column=eq.in_progress" \
  -H "Authorization: Bearer $TOKEN" | jq .
```
