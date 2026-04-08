# phpKanMaster — Completion Design Spec

**Date:** 2026-04-08  
**Scope:** Complete the remaining frontend UI and backend notification system. Infrastructure (Docker, Laravel auth, PostgREST schema, App.Api) is already in place.

---

## What's Already Done

- Laravel 12.x + single-user auth (`auth:single` guard)
- PostgreSQL schema: `tasks`, `categories`, `task_files`
- PostgREST anon role CRUD permissions
- `App.Api` — all PostgREST fetch wrappers
- Basic 5-column board layout in `kanban.blade.php`
- `routes/console.php` scheduler stub (`reminders:send` every minute)

---

## What This Spec Covers

Two independent tracks:

1. **Frontend completion** — fix broken `app.js` structure, implement all missing UI modules, update `kanban.blade.php`
2. **Notification system** — `Task` model, `SendReminders` command, `TaskReminder` notification

---

## Track 1: Frontend

### Problem with Current app.js

The current file has a malformed JS object: `App.Board`, `App.Modal`, `App.DnD`, `App.Alerts` are assigned with dot-notation *inside* an unclosed `window.App = {` literal, which is invalid. Additionally, only `App.Api` and partial `App.Board.render()` are implemented — DnD, modals, category management, event handlers, and plugin initialization are all missing.

**Solution:** Full rewrite as a single clean file.

### app.js Structure

```
window.App = {
  Api:    { baseUrl, request, getTasks, createTask, updateTask, deleteTask,
            getCategories, createCategory, updateCategory, deleteCategory,
            uploadFile, deleteFile }
  Board:  { currentFilter, init, render, renderFilters, createTaskCard, updateCounts }
  DnD:    { init }
  Modal:  {
    Task:     { open(taskId?), save, _populateCategories }
    Category: { open, saveNew }
  }
  Alerts: { Toast, Confirm }
}

// Event delegation (outside App object, at bottom of file):
$(document).on('click', '[data-action="edit"]', ...)
$(document).on('click', '[data-action="delete"]', ...)
$(document).on('click', '[data-action="delete-cat"]', ...)
$(document).on('input', '[data-action="update-color"]', ...)
$('#saveTaskBtn').on('click', ...)
$('#addCategoryBtn').on('click', ...)
$('#category-filters').on('click', 'button', ...)

// Initialization:
$(document).ready(async () => {
  App.Modal.Task._initPlugins();
  await App.Board.init();
  App.DnD.init();
});
```

### App.Board

- `init()` — calls `renderFilters()` then `render()`
- `render()` — fetches tasks + categories, builds `categoryMap`, empties all `.task-list` containers, filters by `currentFilter`, appends cards, calls `updateCounts()`
- `renderFilters()` — fetches categories, injects filter pills into `#category-filters`; "All" pill always present; click handler sets `currentFilter` and calls `render()`
- `createTaskCard(task, category)` — returns jQuery element; priority border via `border-danger/warning/info`; category badge colored with `category.color`; due date shown if set; dropdown menu with Edit and Delete actions
- `updateCounts()` — updates `.task-count` badge on each `.kanban-column`

### App.DnD

- `init()` — calls `$('.task-list').sortable({ connectWith: '.task-list', ... })`
- `update` callback: reads new column from `card.closest('.kanban-column').data('column')`, reads position from `card.index()`, calls `App.Api.updateTask(id, { task_column, position })`
- On error: shows Toast error, calls `App.Board.render()` to revert

### App.Modal.Task

- `open(taskId = null)` — resets form, populates category `<select>` via `_populateCategories()`. If `taskId` provided, fetches task via `App.Api.request('/tasks?id=eq.{id}&select=*')`, populates all fields including Summernote content and Flatpickr values. Sets modal title to "Edit Task" or "Add Task".
- `save()` — reads FormData from `#taskForm`, gets Summernote HTML for description, strips empty optional fields (`category_id`, `due_date`, `reminder_at`), calls `createTask` or `updateTask`, hides modal, re-renders board.
- `_initPlugins()` — initializes Summernote on `#summernote` (toolbar: bold/italic/underline, lists, paragraph; height 200); initializes Flatpickr on `.datepicker` inputs (format `Y-m-d H:i` for datetime fields, `Y-m-d` for date fields).
- `_populateCategories()` — fetches categories, populates `#categorySelect` with "None" option + all categories.

### App.Modal.Category

- `open()` — fetches categories, renders list in `#categoryList` (each row: color picker, name, delete button), shows modal.
- `saveNew()` — reads `#newCatName` and `#newCatColor`, calls `createCategory`, refreshes list.

### App.Alerts

```javascript
Toast: Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false,
                    timer: 3000, timerProgressBar: true, theme: 'dark' })
Confirm: Swal.mixin({ theme: 'dark', showCancelButton: true,
                      confirmButtonColor: '#ef4444', cancelButtonColor: '#252d3d' })
```

### kanban.blade.php Changes

**Add CDN libraries** (before closing `</body>`):
- jQuery UI (CDN, for Sortable)
- Summernote 0.9 CSS + JS (CDN)
- Flatpickr CSS + JS (CDN)
- SweetAlert2 JS (CDN)
- Font Awesome 6 CSS (CDN, for calendar icon on due dates)

**Add HTML sections:**
1. Action buttons bar (above board): `+ Add Task` → `App.Modal.Task.open()`, `Categories` → `App.Modal.Category.open()`
2. Category filter bar: `<div id="category-filters">` with "All" pill pre-rendered
3. Task modal `#taskModal` (Bootstrap `modal-lg`): title, description (Summernote), priority, category, due_date, task_column, reminder_at + pushover_priority section
4. Category modal `#categoryModal`: list container `#categoryList`, new category form (name input + color picker), Add button `#addCategoryBtn`

**Column CSS** (inline `<style>` in blade): `.kanban-column` fixed width 300px, flex column, scroll; `.task-list` flex-grow with overflow-y; `.ui-state-highlight` dashed drop placeholder; `.task-card` cursor grab.

---

## Track 2: Notification System

### app/Models/Task.php

Eloquent model. Table: `tasks`. No timestamps (schema has none). Used only by `SendReminders` — read-only queries. Primary key is UUID (`id`), non-incrementing.

```php
protected $keyType = 'string';
public $incrementing = false;
public $timestamps = false;
protected $casts = ['reminder_at' => 'datetime'];
```

### app/Console/Commands/SendReminders.php

Command signature: `reminders:send`

Logic:
1. Query tasks where `reminder_at` is between `now() - 1 minute` and `now()` (1-minute window matching scheduler frequency).
2. Instantiate `$notifier = new TaskReminder()`. For each due task, call the enabled channel methods directly on `$notifier`.
3. After sending, set `reminder_at = null` on the task (prevents re-fire on next scheduler tick).
4. Log count: `$this->info("Sent {$count} reminder(s)")`.

### app/Notifications/TaskReminder.php

Plain PHP service class (does NOT extend Laravel's `Notification` — no `Notifiable` user model exists). Constructor takes no arguments; delivery methods receive a `Task` instance.

Three delivery methods — each only runs if its config flag is `true`:

- **`sendViaPushover(Task $task)`** — HTTP POST to `https://api.pushover.net/1/messages.json` using Laravel HTTP client. Payload: `token` (PUSHOVER_TOKEN), `user` (PUSHOVER_USER_KEY), `title` (task title), `message` (first 200 chars of `strip_tags($task->description)`), `priority` (task `pushover_priority`, default 0). Missing credentials: log warning and return.
- **`sendViaTwilio(Task $task)`** — HTTP POST to `https://api.twilio.com/2010-04-01/Accounts/{SID}/Messages.json` with Basic auth (SID + token). Fields: `From` (TWILIO_FROM), `To` (TWILIO_TO), `Body` "Reminder: {task title}". Missing credentials: log warning and return.
- **`sendViaRocketChat(Task $task)`** — HTTP POST to ROCKETCHAT_URL. JSON body `{ "text": "🔔 Reminder: {task title}" }`. Missing config: log warning and return.

### config/notifications.php

```php
return [
    'pushover'   => env('NOTIFY_PUSHOVER', false),
    'twilio'     => env('NOTIFY_TWILIO', false),
    'rocketchat' => env('NOTIFY_ROCKETCHAT', false),
];
```

---

## Out of Scope

- `task_files` attachments — DB schema exists, no UI planned
- `parent_id` sub-tasks — DB schema exists, no UI planned
- Recurring tasks spec (`2026-04-08-kanban-recurring-tasks.md`) — separate future work

---

## Files Changed / Created

| File | Action |
|------|--------|
| `public/assets/js/app.js` | Rewrite |
| `resources/views/kanban.blade.php` | Modify (CDN libs, modals, filter bar, buttons) |
| `app/Models/Task.php` | Create |
| `app/Console/Commands/SendReminders.php` | Create |
| `app/Notifications/TaskReminder.php` | Create |
| `config/notifications.php` | Create |

No database migrations required. No changes to routes, auth, or Docker config.
