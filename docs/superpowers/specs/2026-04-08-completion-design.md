# phpKanMaster — Completion Design Spec

**Date:** 2026-04-08
**Supersedes:** partial spec from same date
**Sources:** `kanban-design.md`, `kanban-recurring-tasks.md`, brainstorming session
**Scope:** Complete the remaining frontend UI, notification system, and recurring tasks feature. Infrastructure (Docker, Laravel auth, PostgREST schema, App.Api) is already in place.

---

## What's Already Done

- Laravel 12.x + single-user auth (`auth:single` guard)
- PostgreSQL schema: `tasks` (with `reminder_sent`, `pushover_retry`, `pushover_expire`, `parent_id`), `categories`, `task_files`
- PostgREST anon role CRUD permissions on the above tables
- `App.Api` — all PostgREST fetch wrappers (tasks, categories, task_files)
- Basic 5-column board layout in `kanban.blade.php`
- `routes/console.php` scheduler stub (`reminders:send` every minute)

---

## What This Spec Covers

Three tracks:

1. **Frontend completion** — fix broken `app.js` structure, implement all missing UI modules, update `kanban.blade.php`
2. **Notification system** — `Task` model, `SendReminders` command, `TaskReminder` notification using `laravel-notification-channels` packages
3. **Recurring tasks + notification opt-out** — DB migrations, `RecurrenceRule` model, `App.Recurrence` module, bell icon, recurrence section in modal

---

## Track 1: Frontend

### Problem with Current app.js

The current file has a malformed JS object: `App.Board` is assigned with dot-notation inside an unclosed `window.App = {` literal. DnD, modals, category management, event handlers, and plugin initialization are all missing.

**Solution:** Full clean rewrite as a single `public/assets/js/app.js`.

### app.js Module Structure

```
window.App = {
  Api: {
    baseUrl, request,
    getTasks, createTask, updateTask, deleteTask,
    getCategories, createCategory, updateCategory, deleteCategory,
    uploadFile, deleteFile,
    getRecurrenceRule, createRecurrenceRule, updateRecurrenceRule, deleteRecurrenceRule
  }
  Board: {
    currentFilter,
    init, render, renderFilters, createTaskCard, updateCounts
  }
  DnD: { init }
  Modal: {
    Task:        { open(taskId?), save, _populateCategories, _initPlugins, _loadRecurrence }
    Category:    { open, saveNew }
  }
  Recurrence: {
    buildRRule(formData),
    toggleNotifications(taskId, currentState),
    updateBellIcon(taskId, muted)
  }
  Alerts: { Toast, Confirm }
}

// Event delegation at file bottom:
$(document).on('click', '[data-action="edit"]', ...)
$(document).on('click', '[data-action="delete"]', ...)
$(document).on('click', '[data-action="toggle-bell"]', ...)
$(document).on('click', '[data-action="delete-cat"]', ...)
$(document).on('input',  '[data-action="update-color"]', ...)
$('#saveTaskBtn').on('click', ...)
$('#addCategoryBtn').on('click', ...)

// Init:
$(document).ready(async () => {
  App.Modal.Task._initPlugins();
  await App.Board.init();
  App.DnD.init();
});
```

### App.Api Additions

Add to existing `App.Api`:

- `getRecurrenceRule(taskId)` — `GET /recurrence_rules?task_id=eq.{taskId}&select=*`
- `createRecurrenceRule(data)` — `POST /recurrence_rules`
- `updateRecurrenceRule(id, data)` — `PATCH /recurrence_rules?id=eq.{id}`
- `deleteRecurrenceRule(id)` — `DELETE /recurrence_rules?id=eq.{id}`

Also update `getTasks()` to include `disable_notifications`:
`GET /tasks?select=*&order=task_column.asc,position.asc`
(PostgREST returns all columns including `disable_notifications` by default with `select=*`)

### App.Board

- `init()` — calls `renderFilters()` then `render()`
- `render()` — fetches tasks + categories in parallel, builds `categoryMap`, clears `.task-list` containers, applies `currentFilter`, appends cards, calls `updateCounts()`
- `renderFilters()` — fetches categories, injects filter pills into `#category-filters`; "All" pill always present; click sets `currentFilter` + calls `render()`
- `createTaskCard(task, category)` — returns jQuery element with:
  - Priority left border: `border-danger` (high) / `border-warning` (medium) / `border-info` (low)
  - Category badge colored with `category.color`
  - Due date if set
  - Bell icon: 🔔 if `!disable_notifications`, 🔕 if `disable_notifications`; `data-action="toggle-bell"` attribute
  - Subtask count badge (`↳ N subtasks`) when `childCount > 0` (computed from flat tasks array grouped by `parent_id`)
  - Done column cards: `opacity: 0.6`, title `text-decoration: line-through`
  - Dropdown menu: Edit, Delete
- `updateCounts()` — updates `.task-count` badge on each `.kanban-column`

### App.DnD

- `init()` — `$('.task-list').sortable({ connectWith: '.task-list', handle: '.task-card', placeholder: 'ui-state-highlight' })`
- `update` callback: reads new column from `card.closest('.kanban-column').data('column')`, reads position from `card.index()`, calls `App.Api.updateTask(id, { task_column, position })`
- On error: Toast error, call `App.Board.render()` to revert

### App.Modal.Task

- `_initPlugins()` — initialize Summernote on `#summernote` (toolbar: bold/italic/underline, lists, paragraph; height 200px; dark theme); initialize Flatpickr on `[name="due_date"]` (format `Y-m-d`); Flatpickr on `[name="reminder_at"]` (format `Y-m-d H:i`, enableTime)
- `_populateCategories()` — fetches categories, populates `#categorySelect` with "None" + all categories
- `_loadRecurrence(taskId)` — fetches recurrence rule for task, pre-fills recurrence section if found; sets `this._recurrenceRuleId`
- `open(taskId = null)` — reset form, call `_populateCategories()`. If `taskId`: fetch task, populate all fields including Summernote + Flatpickr values + pushover retry/expire, call `_loadRecurrence(taskId)`. Show modal.
- `save()` — read FormData, get Summernote HTML, strip empty optional fields. If `id` present: `updateTask`, else `createTask`. If recurrence checkbox checked: `createRecurrenceRule` / `updateRecurrenceRule`; if unchecked and rule existed: `deleteRecurrenceRule`. Hide modal, re-render board.
- Emergency pushover priority: show/hide `#pushoverRetry` and `#pushoverExpire` fields when priority = 2 is selected

### App.Modal.Category

- `open()` — fetches categories, renders list in `#categoryList`, shows modal
- `saveNew()` — reads `#newCatName` + `#newCatColor`, calls `createCategory`, refreshes list

### App.Recurrence

- `buildRRule(formData)` — builds RRULE JSON object from form fields (pattern, interval, weekdays, end date). Returns JSON string for storing in `recurrence_rules.rrule`.
- `toggleNotifications(taskId, currentState)` — PATCH task `disable_notifications`, show toast, call `updateBellIcon`
- `updateBellIcon(taskId, muted)` — update bell icon emoji on the task card DOM element

### App.Alerts

```javascript
Toast: Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false,
                    timer: 3000, timerProgressBar: true, theme: 'dark' })
Confirm: Swal.mixin({ theme: 'dark', showCancelButton: true,
                      confirmButtonColor: '#ef4444', cancelButtonColor: '#252d3d' })
```

SweetAlert2 usage:

| Trigger | Type |
|---------|------|
| Task saved/created | Toast success |
| API error | Toast error |
| Delete task (no children) | Confirm — "Delete task? This cannot be undone." |
| Delete task (has children) | Confirm — HTML list of all child task titles |
| Bell toggle | Confirm — "Mute notifications for this task?" |
| Delete category | Confirm — "Tasks in this category will become uncategorized." |

### Blade View Changes (kanban.blade.php)

**CDN libraries to add** (in `<head>` for CSS, before `</body>` for JS):

CSS:
- Summernote 0.9 Bootstrap 5 theme
- Flatpickr (dark theme)
- Font Awesome 6 (for calendar icon)

JS (after Bootstrap bundle):
- jQuery UI (CDN, for Sortable)
- Summernote 0.9 JS
- Flatpickr JS
- SweetAlert2 JS

**HTML additions:**

1. Action buttons bar (between navbar and board):
   ```html
   <div class="d-flex justify-content-end gap-2 mb-3 px-3">
     <button onclick="App.Modal.Task.open()">+ Add Task</button>
     <button onclick="App.Modal.Category.open()">Categories</button>
   </div>
   ```

2. Category filter bar:
   ```html
   <div id="category-filters" class="d-flex gap-2 px-3 mb-3">
     <button class="filter-pill active" data-filter="all">All</button>
   </div>
   ```

3. Task modal `#taskModal` (Bootstrap `modal-lg`, dark theme) — fields:
   - Hidden `id`
   - Title (required text input)
   - Description (Summernote `#summernote`)
   - Priority select (Low / Medium / High)
   - Category select (`#categorySelect`)
   - Due Date (Flatpickr date `[name="due_date"]`)
   - Column select (new / in_progress / review / on_hold / done)
   - Pushover Reminder section: `reminder_at` (Flatpickr datetime), priority select (Lowest −2 / Low −1 / Normal 0 / High 1 / Emergency 2), `#pushoverRetry` + `#pushoverExpire` inputs (shown only when Emergency selected)
   - Recurrence section (collapsible): "Repeat this task" checkbox → pattern dropdown + interval + weekday buttons (M T W T F S S, shown for Weekly) + end radio (Never / On date) + live preview text
   - Subtasks section: list of direct child tasks (title + column badge), "New subtask" button, "Link existing" searchable dropdown, unlink (✕) per row

4. Category modal `#categoryModal` — list container `#categoryList`, new category form (name + color picker + Add button `#addCategoryBtn`)

**Inline CSS** (in blade `<style>` block):
```css
.kanban-column { min-width: 300px; max-width: 300px; background: #1a1d23;
                 border-radius: 0.5rem; padding: 1rem;
                 height: calc(100vh - 150px); display: flex; flex-direction: column; }
.task-list     { flex-grow: 1; overflow-y: auto; min-height: 100px; }
.ui-state-highlight { height: 100px; background: rgba(255,255,255,0.1) !important;
                       border: 2px dashed #444; border-radius: 0.5rem; margin-bottom: 1rem; }
.task-card     { cursor: grab; transition: transform 0.1s; }
.task-card:active { cursor: grabbing; transform: scale(0.98); }
```

---

## Track 2: Notification System

### Composer Packages

```bash
composer require laravel-notification-channels/pushover \
                 laravel-notification-channels/twilio \
                 laravel-notification-channels/rocket-chat
```

### config/notifications.php

```php
return [
    'channels' => [
        'pushover'   => env('NOTIFY_PUSHOVER', false),
        'twilio'     => env('NOTIFY_TWILIO', false),
        'rocketchat' => env('NOTIFY_ROCKETCHAT', false),
    ],
];
```

### config/services.php (additions)

```php
'pushover' => [
    'token'    => env('PUSHOVER_TOKEN'),
    'user_key' => env('PUSHOVER_USER_KEY'),
],
'twilio' => [
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token'  => env('TWILIO_AUTH_TOKEN'),
    'from'        => env('TWILIO_FROM'),
    'to'          => env('TWILIO_TO'),       // recipient phone number
],
'rocketchat' => [
    'url'     => env('ROCKETCHAT_URL'),
    'token'   => env('ROCKETCHAT_TOKEN'),
    'channel' => env('ROCKETCHAT_CHANNEL', '#general'),
],
```

### app/Models/Task.php

Eloquent model. Table: `tasks`. No auto-incrementing UUID primary key.

```php
protected $keyType = 'string';
public $incrementing = false;
public $timestamps = false;       // schema uses manual created_at/updated_at
protected $casts = [
    'reminder_at'            => 'datetime',
    'reminder_sent'          => 'boolean',
    'disable_notifications'  => 'boolean',
];

// Relationship
public function recurrenceRule(): HasOne { return $this->hasOne(RecurrenceRule::class); }
public function children(): HasMany     { return $this->hasMany(Task::class, 'parent_id'); }
```

### app/Models/RecurrenceRule.php

```php
protected $keyType = 'string';
public $incrementing = false;
public $timestamps = false;
protected $casts = [
    'next_occurrence_at' => 'datetime',
    'active'             => 'boolean',
];
```

### app/Notifications/TaskReminder.php

Extends Laravel's `Notification`. Uses `laravel-notification-channels` packages.

```php
public function via(object $notifiable): array
{
    $channels = [];
    if (config('notifications.channels.pushover'))   $channels[] = PushoverChannel::class;
    if (config('notifications.channels.twilio'))     $channels[] = TwilioChannel::class;
    if (config('notifications.channels.rocketchat')) $channels[] = RocketChatWebhookChannel::class;
    return $channels;
}

public function toPushover(object $notifiable): PushoverMessage
{
    return PushoverMessage::create($this->task->title)
        ->priority($this->task->pushover_priority)
        ->when($this->task->pushover_priority === 2, fn($m) =>
            $m->retry($this->task->pushover_retry ?? 30)
              ->expireAfter($this->task->pushover_expire ?? 3600)
        );
}

public function toTwilio(object $notifiable): TwilioSmsMessage
{
    return (new TwilioSmsMessage())
        ->content("Reminder: {$this->task->title}");
}

public function toRocketChat(object $notifiable): RocketChatMessage
{
    return RocketChatMessage::create(
        "📋 Reminder: *{$this->task->title}* — due {$this->task->due_date}"
    );
}
```

### app/Console/Commands/SendReminders.php

Command signature: `reminders:send`

Full logic (incorporating recurring tasks):

```
1. Query: tasks where reminder_at <= now() AND reminder_sent = false
2. For each task:
   a. If !disable_notifications → dispatch via Notification::route(...)
   b. Check for active RecurrenceRule
   c. If rule exists and not past end_date:
      - Parse RRULE using rlanvin/php-rrule
      - Calculate next occurrence after current reminder_at
      - replicate() task with new reminder_at = next occurrence, reminder_sent = false
      - Clone direct children (parent_id → new task id)
      - Update rule.next_occurrence_at to occurrence after the one just created
   d. task->update(['reminder_sent' => true])
3. $this->info("Sent {$count} reminder(s)")
```

Notification routing (no Notifiable user model — use anonymous route):
```php
Notification::route('pushover', config('services.pushover.user_key'))
    ->route('twilio', config('services.twilio.to'))
    ->route('rocketchat', null)
    ->notify(new TaskReminder($task));
```

---

## Track 3: DB Migrations (Laravel)

Two new Laravel migrations (run via `php artisan migrate`):

### Migration 1: Add disable_notifications to tasks

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->boolean('disable_notifications')->default(false)->after('pushover_expire');
});
```

### Migration 2: Create recurrence_rules table

```php
Schema::create('recurrence_rules', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $table->uuid('task_id');
    $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
    $table->text('rrule');
    $table->timestampTz('next_occurrence_at');
    $table->date('end_date')->nullable();
    $table->boolean('active')->default(true);
    $table->timestampTz('created_at')->useCurrent();
    $table->timestampTz('updated_at')->useCurrent();
    $table->index(['active', 'next_occurrence_at']);
});
```

### PostgREST permissions (append to docker/db/init/03-permissions.sql)

```sql
-- Grant CRUD on recurrence_rules to anon role
grant select, insert, update, delete on recurrence_rules to anon;
```

Note: this only applies to fresh Docker volumes. For running containers, run the grant manually or via migration.

### composer.json addition

```bash
composer require rlanvin/php-rrule
```

---

## Files Changed / Created

| File | Action |
|------|--------|
| `public/assets/js/app.js` | Rewrite |
| `resources/views/kanban.blade.php` | Modify (CDN libs, modals, filter bar, buttons, recurrence section, subtasks section) |
| `app/Models/Task.php` | Create |
| `app/Models/RecurrenceRule.php` | Create |
| `app/Console/Commands/SendReminders.php` | Create |
| `app/Notifications/TaskReminder.php` | Create |
| `config/notifications.php` | Create |
| `config/services.php` | Modify (add pushover, twilio, rocketchat keys) |
| `database/migrations/*_add_disable_notifications_to_tasks.php` | Create |
| `database/migrations/*_create_recurrence_rules_table.php` | Create |
| `docker/db/init/03-permissions.sql` | Modify (add recurrence_rules grant) |
| `composer.json` / `composer.lock` | Modify (add 4 packages) |

No changes to routes, auth, or Caddy config.

---

## Out of Scope

- `task_files` attachments in WYSIWYG — DB schema exists, upload flow not implemented
- Recurring task "edit all future occurrences" — single occurrence edit only
- Recurrence exclusion dates
- Recurrence preview calendar view
