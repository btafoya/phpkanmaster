# phpKanMaster Agent MCP Server — User Guide

phpKanMaster includes a custom MCP (Model Context Protocol) server implementation that allows external agents (like Openclaw) to authenticate and query task data via a JWT-based API.

---

## Overview

The Agent MCP Server consists of:

| Component | Purpose |
|-----------|---------|
| `POST /api/agent/token` | Issues short-lived JWT tokens for agent authentication |
| `active_tasks_with_notes` PostgreSQL view | Provides agent-readable access to active tasks with notes |
| `agent` PostgreSQL role | Isolated role with SELECT-only access to the view |

---

## Architecture

```
Openclaw Agent
    │
    │  POST /api/agent/token (username + password)
    ▼
Laravel (issues JWT with role=agent, exp=1h)
    │
    │  Bearer JWT
    ▼
PostgREST (/api/active_tasks_with_notes)
    │  validates JWT → switches to `agent` role
    ▼
PostgreSQL (SELECT on active_tasks_with_notes view)
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

The token endpoint is rate-limited: **5 requests per minute** per IP.

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

- The `agent` PostgreSQL role has **no write permissions** — only SELECT
- Tokens expire after 1 hour — implement refresh logic in your agent
- The rate limit (5/min) helps prevent brute-force attacks
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

---

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
