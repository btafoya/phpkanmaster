# PostgREST Schema + API Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the PostgreSQL database schema and the frontend JavaScript API module to enable full CRUD operations via PostgREST.

**Architecture:** The database schema is implemented directly in PostgreSQL. PostgREST is configured to expose these tables via a REST API. The Laravel frontend uses a dedicated `App.Api` module to interact with this API via a Caddy reverse proxy.

**Tech Stack:** PostgreSQL 17, PostgREST, JavaScript (jQuery 4.0), Caddy.

---

## File Structure

**Database:**
- `docker/db/init/02-schema.sql` (New) - Table definitions and seed data.
- `docker/db/init/03-permissions.sql` (New) - PostgREST role grants.

**Frontend:**
- `public/assets/js/app.js` (Modify) - Implementation of the `App.Api` object.

---

## Task 1: Create Database Schema

**Files:**
- Create: `docker/db/init/02-schema.sql`

- [ ] **Step 1: Write schema definition**

```sql
-- docker/db/init/02-schema.sql

-- Categories
create table categories (
  id    uuid primary key default gen_random_uuid(),
  name  text not null unique,
  color text not null default '#6c757d'
);

insert into categories (name, color) values
  ('Personal', '#5b8dee'),
  ('Business', '#f0a500'),
  ('Music',    '#e83e8c'),
  ('Home',     '#28c76f');

-- Tasks
create table tasks (
  id                uuid primary key default gen_random_uuid(),
  title             text not null,
  description       text,
  due_date          date,
  priority          text not null default 'medium'
                      check (priority in ('low', 'medium', 'high')),
  task_column       text not null default 'new'
                      check (task_column in ('new', 'in_progress', 'review', 'on_hold', 'done')),
  position          integer not null default 0,
  category_id       uuid references categories(id) on delete set null,
  parent_id         uuid references tasks(id) on delete cascade,
  reminder_at       timestamptz,
  reminder_sent     boolean not null default false,
  pushover_priority integer not null default 0
                      check (pushover_priority between -2 and 2),
  pushover_retry    integer default 30,
  pushover_expire   integer default 3600,
  created_at        timestamptz not null default now(),
  updated_at        timestamptz not null default now()
);

-- Task file attachments
create table task_files (
  id         uuid primary key default gen_random_uuid(),
  task_id    uuid references tasks(id) on delete cascade,
  filename   text not null,
  mime_type  text not null,
  data       text not null,
  created_at timestamptz not null default now()
);
```

- [ ] **Step 2: Apply schema to database**

Run: `docker compose exec db psql -U kanban -d kanban -f /docker-entrypoint-initdb.d/02-schema.sql`
Expected: Tables created successfully.

- [ ] **Step 3: Commit**

```bash
git add docker/db/init/02-schema.sql
git commit -m "feat: add PostgreSQL schema for categories, tasks, and task_files"
```

---

## Task 2: Configure PostgREST Permissions

**Files:**
- Create: `docker/db/init/03-permissions.sql`

- [ ] **Step 1: Write permission grants**

```sql
-- docker/db/init/03-permissions.sql

-- Grant all CRUD permissions to the anon role
grant select, insert, update, delete on tasks, categories, task_files to anon;

-- Ensure anon can use the uuid-ossp extension if needed (already usually available in PG17 gen_random_uuid)
```

- [ ] **Step 2: Apply permissions**

Run: `docker compose exec db psql -U kanban -d kanban -f /docker-entrypoint-initdb.d/03-permissions.sql`
Expected: Grants applied successfully.

- [ ] **Step 3: Commit**

```bash
git add docker/db/init/03-permissions.sql
git commit -m "feat: grant CRUD permissions to PostgREST anon role"
```

---

## Task 3: Implement `App.Api` Module

**Files:**
- Modify: `public/assets/js/app.js`

- [ ] **Step 1: Implement base request helper**

Add to `App.Api`:

```javascript
App.Api = {
    baseUrl: window.POSTGREST_URL || '/api',

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const response = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'Prefer': 'return=representation', // Get the object back on POST/PATCH
                ...options.headers,
            },
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || `API Error: ${response.status}`);
        }

        return response.json();
    },
```

- [ ] **Step 2: Implement Task CRUD**

Add to `App.Api`:

```javascript
    async getTasks() {
        return this.request('/tasks?select=*&order=task_column.asc,position.asc');
    },

    async createTask(data) {
        return this.request('/tasks', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    },

    async updateTask(id, data) {
        return this.request(`/tasks?id=eq.${id}`, {
            method: 'PATCH',
            body: JSON.stringify(data),
        });
    },

    async deleteTask(id) {
        return this.request(`/tasks?id=eq.${id}`, {
            method: 'DELETE',
        });
    },
```

- [ ] **Step 3: Implement Category CRUD**

Add to `App.Api`:

```javascript
    async getCategories() {
        return this.request('/categories?select=*&order=name.asc');
    },

    async createCategory(data) {
        return this.request('/categories', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    },

    async updateCategory(id, data) {
        return this.request(`/categories?id=eq.${id}`, {
            method: 'PATCH',
            body: JSON.stringify(data),
        });
    },

    async deleteCategory(id) {
        return this.request(`/categories?id=eq.${id}`, {
            method: 'DELETE',
        });
    },
```

- [ ] **Step 4: Implement File Upload**

Add to `App.Api`:

```javascript
    async uploadFile(data) {
        return this.request('/task_files', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    },

    async deleteFile(id) {
        return this.request(`/task_files?id=eq.${id}`, {
            method: 'DELETE',
        });
    },
}
```

- [ ] **Step 5: Commit**

```bash
git add public/assets/js/app.js
git commit -m "feat: implement App.Api for PostgREST CRUD operations"
```

---

## Task 4: Verify API Connectivity and CRUD

**Files:**
- No file changes — validation task

- [ ] **Step 1: Verify Connectivity via Curl**

Run: `curl -I http://localhost:8080/api/tasks`
Expected: `200 OK` (empty array `[]`)

- [ ] **Step 2: Test Category Creation via Curl**

Run:
```bash
curl -X POST http://localhost:8080/api/categories \
     -H "Content-Type: application/json" \
     -d '{"name": "Testing", "color": "#ff0000"}'
```
Expected: JSON response with the created category.

- [ ] **Step 3: Test Task Creation via Curl**

Run:
```bash
curl -X POST http://localhost:8080/api/tasks \
     -H "Content-Type: application/json" \
     -d '{"title": "API Test Task", "priority": "high", "task_column": "new"}'
```
Expected: JSON response with the created task.

- [ ] **Step 4: Test Task Update via Curl**

Run:
```bash
# Replace {id} with actual UUID from Step 3
curl -X PATCH http://localhost:8080/api/tasks?id=eq.{id} \
     -H "Content-Type: application/json" \
     -d '{"title": "Updated API Task"}'
```
Expected: JSON response with updated task.

- [ ] **Step 5: Verify via Browser Console**

1. Open `http://localhost:8080` (authenticated)
2. Open DevTools Console
3. Run: `App.Api.getCategories().then(console.log)`
4. Run: `App.Api.getTasks().then(console.log)`

Expected: Both promises resolve to arrays of data.

- [ ] **Step 6: Commit**

```bash
git commit -m "test: verify PostgREST API connectivity and CRUD operations"
```

---

## Self-Review Checklist

- [ ] All SQL statements match the design spec.
- [ ] PostgREST endpoints follow the `/api/*` path through Caddy.
- [ ] `App.Api` handles errors via `throw new Error` for consistency with `App.Alerts.Toast`.
- [ ] `Prefer: return=representation` is used to get created/updated objects back.
- [ ] UUIDs are handled correctly by PostgREST.

---

## Next Plan

After this plan completes, the next plan is **Kanban Board UI Implementation**, which implements `App.Board`, `App.DnD`, and the core rendering logic.
