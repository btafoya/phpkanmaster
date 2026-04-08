# phpKanMaster — Design Spec

**Date:** 2026-04-08
**Stack:** PHP · Bootstrap 5.3 · jQuery 4.0 · PostgREST · PostgreSQL
**Scope:** Personal single-board Kanban task manager with Pushover reminders

---

## 1. Architecture

### Approach
Pure SPA with a PHP front controller. PHP's only responsibilities are authentication and serving the HTML shell. All Kanban data flows directly between the browser and PostgREST. No PHP touches task data.

### File Structure

```
phpkanmaster/
├── public/                   # Web root (document root points here)
│   ├── index.php             # Front controller — auth check + SPA shell
│   ├── .htaccess             # Rewrite all requests to index.php
│   └── assets/
│       ├── css/app.css       # Custom styles on top of Bootstrap 5.3
│       └── js/app.js         # All jQuery 4.0 Kanban logic
├── bin/
│   └── send-reminders.php    # Cron script — Pushover notification sender
└── config.php                # Outside web root — PostgREST URL, credentials, Pushover keys
```

### Routing (index.php)

| Request | Action |
|---------|--------|
| `GET /` | If `$_SESSION['authenticated']` → render SPA shell (inject PostgREST base URL as JS var); else → show login form |
| `POST /?action=login` | Validate credentials against bcrypt hash in `config.php` → set session → redirect to `/` |
| `GET /?action=logout` | Destroy session → redirect to `/` |

### Auth Model
- Credentials: hardcoded username + bcrypt hash in `config.php`
- PHP session gate controls page access
- PostgREST runs with a single `anon` role (full CRUD) — the session gate is the only auth layer
- `config.php` is outside the web root; never web-accessible

---

## 2. Database Schema

```sql
-- Categories (user-managed)
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
  id               uuid primary key default gen_random_uuid(),
  title            text not null,
  description      text,                    -- HTML from Summernote WYSIWYG
  due_date         date,
  priority         text not null default 'medium'
                     check (priority in ('low', 'medium', 'high')),
  task_column      text not null default 'new'
                     check (task_column in ('new', 'in_progress', 'review', 'on_hold', 'done')),
  position         integer not null default 0,
  category_id      uuid references categories(id) on delete set null,
  parent_id        uuid references tasks(id) on delete cascade,  -- unlimited nesting
  reminder_at      timestamptz,
  reminder_sent    boolean not null default false,
  pushover_priority integer not null default 0
                     check (pushover_priority between -2 and 2),
  pushover_retry   integer default 30,     -- seconds; required when pushover_priority = 2
  pushover_expire  integer default 3600,   -- seconds; required when pushover_priority = 2
  created_at       timestamptz not null default now(),
  updated_at       timestamptz not null default now()
);

-- Task file attachments (images/files from WYSIWYG)
create table task_files (
  id         uuid primary key default gen_random_uuid(),
  task_id    uuid references tasks(id) on delete cascade,
  filename   text not null,
  mime_type  text not null,
  data       text not null,   -- base64-encoded file content
  created_at timestamptz not null default now()
);

-- Permissions
grant select, insert, update, delete on tasks, categories, task_files to anon;
```

### PostgREST Endpoints Used

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/tasks?order=task_column,position` | Load all tasks on boot |
| `GET` | `/categories?order=name` | Load categories on boot |
| `POST` | `/tasks` | Create task |
| `PATCH` | `/tasks?id=eq.{id}` | Update task (edit, task_column change, reorder) |
| `DELETE` | `/tasks?id=eq.{id}` | Delete task |
| `POST` | `/task_files` | Upload file/image from WYSIWYG |
| `DELETE` | `/task_files?id=eq.{id}` | Remove attachment |
| `GET` | `/categories` | Category management |
| `POST` | `/categories` | Create category |
| `PATCH` | `/categories?id=eq.{id}` | Edit category |
| `DELETE` | `/categories?id=eq.{id}` | Delete category |

---

## 3. UI Design

### Board Layout
- Five columns side-by-side: **New · In Progress · Review · On Hold · Done**
- Desktop: all columns visible, horizontal scroll if narrow
- Mobile: columns overflow-x scroll (swipe to reveal)
- Each column shows a task count badge
- "+ Add card" shortcut at the bottom of each column

### Navbar
- App title (left)
- Category filter pills: All · Personal · Business · Music · Home (client-side filter, no refetch)
- "+ Add Task" button (right)
- Logout link (right)

### Task Cards
- Priority-colored left border: High=red · Medium=amber · Low=green
- Title (bold), truncated description preview
- Category badge (colored pill)
- Due date
- Subtask count indicator (`↳ N subtasks`) when children exist
- Cards in **Done** column: dimmed + strikethrough title

### Add/Edit Task Modal (Summernote WYSIWYG)

Fields:
- **Title** (required)
- **Column** (select)
- **Priority** (select: Low / Medium / High)
- **Category** (select, populated from `/categories`)
- **Due Date** (date picker)
- **Description** — Summernote 0.9 WYSIWYG editor, Bootstrap 5 theme
  - Toolbar: Bold, Italic, Underline, Strikethrough, lists, link, image insert, file attach, code block
  - Images and files dragged into the editor body are uploaded to `/task_files` and embedded as URLs
  - All input/editor text is white (`#ffffff`) on dark background

**Pushover Reminder section:**
- Remind at (datetime)
- Pushover Priority (select with human-readable labels):
  - Lowest (-2) — silent, badge only
  - Low (-1) — silent popup
  - Normal (0) — sound + vibration *(default)*
  - High (1) — bypass quiet hours
  - Emergency (2) — repeat until acknowledged
- Retry every (seconds) — visible + enabled only when Emergency selected, default 30
- Stop after (seconds) — visible + enabled only when Emergency selected, default 3600

**Subtasks section:**
- List of direct children with their current column status
- "New subtask" — opens a nested modal pre-set with `parent_id`
- "Link existing task" — searchable dropdown of all tasks → PATCH selected task's `parent_id`
- Unlink button (✕) on each subtask row

**Actions:**
- Delete task (bottom-left, red) — triggers SweetAlert2 confirm with subtask details
- Cancel / Save task (bottom-right)

---

## 4. JavaScript App Structure

`assets/js/app.js` is organized into five plain-object modules (no build step):

```
App.Api       — PostgREST fetch calls: getTasks, getCategories, createTask,
                updateTask, deleteTask, uploadFile, deleteFile,
                createCategory, updateCategory, deleteCategory
App.Board     — renders columns and cards from flat task array; client-side filter
App.Modal     — open/close/populate add-edit form; Summernote init/destroy
App.DnD       — jQuery UI Sortable init; fires position PATCH batch on drop
App.Alerts    — SweetAlert2 mixin definitions (Toast, Confirm)
```

### Boot Sequence
1. `App.Api.getTasks()` and `App.Api.getCategories()` fire in parallel
2. `App.Board.render(tasks, categories)` builds all 5 columns
3. `App.DnD.init()` activates Sortable on each column
4. Category filter pills bind to `App.Board.filter(categoryId)` — client-side, no refetch

### Drag-and-Drop Reorder
On Sortable `stop` event:
- Collect new card order within the column
- PATCH moved card's `task_column` + `position`
- Batch PATCH sibling `position` values to reflect new order

### Tree Rendering (Subtasks)
- All tasks fetched flat in a single `GET /tasks`
- Tree built client-side: group by `parent_id`
- Cards on the board show only root tasks (no `parent_id`)
- Subtasks shown inside the modal's subtask section
- Unlimited depth supported in data model; UI shows direct children only in the modal list

---

## 5. Notifications & Alerts (SweetAlert2)

### Mixins

```javascript
// Toast — non-blocking feedback, top-right, auto-dismiss after 3s
App.Alerts.Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  theme: 'dark',
  didOpen: (toast) => {
    toast.onmouseenter = Swal.stopTimer
    toast.onmouseleave = Swal.resumeTimer
  }
})

// Confirm — destructive action dialogs
App.Alerts.Confirm = Swal.mixin({
  theme: 'dark',
  showCancelButton: true,
  confirmButtonColor: '#ef4444',
  cancelButtonColor: '#252d3d',
})
```

### Usage Map

| Trigger | Type | Detail |
|---------|------|--------|
| Task saved / created | Toast success | "Task saved" |
| API error | Toast error | Error message from PostgREST |
| Delete task (no subtasks) | Confirm | "Delete task? This cannot be undone." |
| Delete task (has subtasks) | Confirm | Title + HTML list of all descendant task titles |
| Unlink subtask | Confirm | "Remove this subtask link?" |
| Logout | Confirm | "Log out?" |
| Reminder sent successfully (cron) | n/a — server-side only | |

No `alert()`, `confirm()`, or `prompt()` calls anywhere in the codebase.

---

## 6. Pushover Cron Script (`bin/send-reminders.php`)

- Connects to PostgreSQL directly (PDO, credentials from `config.php`)
- Query: `SELECT * FROM tasks WHERE reminder_at <= NOW() AND reminder_sent = false`
- For each task: POST to `https://api.pushover.net/1/messages.json` with:
  - `token` — from `config.php`
  - `user` — from `config.php`
  - `title` — task title
  - `message` — due date + priority label
  - `priority` — `pushover_priority`
  - `retry` — `pushover_retry` (only when `pushover_priority = 2`)
  - `expire` — `pushover_expire` (only when `pushover_priority = 2`)
- On success: `UPDATE tasks SET reminder_sent = true WHERE id = ?`
- Cron entry: `* * * * * php /path/to/phpkanmaster/bin/send-reminders.php`

---

## 7. Category Management

Accessible via a "Manage Categories" modal linked from the navbar or category filter area.

- Lists all categories with color swatches
- Inline edit: name + color picker
- Delete category: SweetAlert2 confirm ("Tasks in this category will become uncategorized")
- Add category: name + color input → `POST /categories`
- All changes reflect immediately in the board's filter pills and task modal dropdowns

---

## 8. Error Handling

- All `App.Api` calls wrapped in `try/catch`
- Network or PostgREST errors surface as `App.Alerts.Toast.fire({ icon: 'error', title: '...' })`
- Form validation (required title) prevents save before any API call
- Cron script logs errors to `stderr` (visible in cron mail or log redirect)

---

## Dependencies

| Library | Version | Purpose |
|---------|---------|---------|
| Bootstrap | 5.3 | UI framework |
| jQuery | 4.0 | DOM + AJAX |
| jQuery UI | latest | Sortable drag-and-drop |
| Summernote | 0.9 | WYSIWYG description editor |
| SweetAlert2 | latest | All alerts, confirms, toasts |
| Flatpickr | latest | Date/datetime pickers |
