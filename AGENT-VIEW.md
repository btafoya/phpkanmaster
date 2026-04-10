# PostgREST View for Openclaw Agent

## Context

The Openclaw agent needs a private, authenticated remote view of active tasks with their notes. Since the existing PostgREST setup uses an `anon` role for all browser traffic, a new **separate PostgreSQL role** (`agent`) will own the view, and only that role can query it. Laravel issues short-lived JWTs that PostgREST validates directly.

## Architecture

```
Openclaw Agent
    │
    │  POST /api/agent/token  (username + password)
    ▼
Laravel (issues JWT with role=agent, exp=1h)
    │
    │  Bearer JWT
    ▼
PostgREST (/active_tasks_with_notes via /api/*)
    │  validates JWT → switches to `agent` role
    ▼
PostgreSQL (SELECT on active_tasks_with_notes)
```

## Database View: `active_tasks_with_notes`

```sql
CREATE OR REPLACE VIEW active_tasks_with_notes AS
SELECT
    t.id,
    t.title,
    t.description,
    t.due_date,
    t.priority,
    t.task_column,
    t.position,
    t.category_id,
    t.parent_id,
    t.reminder_at,
    t.disable_notifications,
    t.created_at,
    t.updated_at,
    COALESCE(
        json_agg(
            json_build_object(
                'id', n.id,
                'content', n.content,
                'created_at', n.created_at,
                'updated_at', n.updated_at
            )
        ) FILTER (WHERE n.id IS NOT NULL),
        '[]'::json
    ) AS notes
FROM tasks t
LEFT JOIN task_notes n ON n.task_id = t.id
WHERE t.task_column != 'done'
GROUP BY t.id
ORDER BY t.position ASC
```

- Notes returned as a JSON array (empty `[]` if no notes)
- Active tasks only (`task_column != 'done'`)
- Ordered by position within column

## PostgreSQL Role

A new `agent` role (no login) is granted `SELECT` on the view. The `kanban_postgrest` user is granted membership in `agent` so PostgREST can `SET ROLE agent` when a valid JWT with `role: agent` is presented.

```sql
CREATE ROLE agent NOLOGIN;
GRANT agent TO kanban_postgrest;
GRANT SELECT ON active_tasks_with_notes TO agent;
```

## JWT Authentication

### Configuration

**`.env`**:
```
JWT_SECRET=phpkanmaster_agent_jwt_secret_32chars_min
```

**`docker-compose.yml`** (postgrest service):
```yaml
PGRST_JWT_SECRET: ${JWT_SECRET}
```

**Important:** After adding `PGRST_JWT_SECRET` to `docker-compose.yml`, you must run:
```bash
docker compose up -d --force-recreate postgrest
```
A plain `docker compose restart` does not re-read `.env` changes.

### Token Endpoint

**`POST /api/agent/token`**

Request:
```json
{ "username": "admin", "password": "..." }
```

Response:
```json
{ "token": "eyJ...", "expires_in": 3600 }
```

The JWT payload:
```json
{
  "iss": "http://localhost:8181",
  "sub": "admin",
  "role": "agent",
  "iat": 1234567890,
  "exp": 1234571490
}
```

PostgREST reads the `role` claim and executes the request as the `agent` PostgreSQL role.

## Files Created/Modified

| File | Action |
|---|---|
| `database/migrations/2026_04_09_110000_create_active_tasks_with_notes_view.php` | Create |
| `database/migrations/2026_04_09_110001_create_agent_role.php` | Create |
| `app/Http/Controllers/AgentTokenController.php` | Create |
| `routes/api.php` | Create |
| `bootstrap/app.php` | Edit — added `api:` route registration |
| `config/app.php` | Edit — added `jwt_secret` |
| `docker-compose.yml` | Edit — added `PGRST_JWT_SECRET` |
| `.env` | Edit — added `JWT_SECRET` |
| `docker/caddy/Caddyfile` | Edit — added `/api/agent/token` handler |
| `composer.json` / `composer.lock` | Edit — added `firebase/php-jwt` |

## Usage by Openclaw Agent

```bash
# Get token
TOKEN=$(curl -s -X POST http://localhost:8181/api/agent/token \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"..."}' | jq -r '.token')

# Query active tasks
curl -s http://localhost:8181/api/active_tasks_with_notes \
  -H "Authorization: Bearer $TOKEN" | jq .

# With specific columns
curl -s "http://localhost:8181/api/active_tasks_with_notes?select=id,title,task_column,notes,priority&order=position.asc" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

## Dependencies

```bash
# Installed via composer
firebase/php-jwt ^7.0
```
