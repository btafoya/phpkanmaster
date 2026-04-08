# phpKanMaster — Design Spec

**Date:** 2026-04-08
**Stack:** Laravel 12.x · PHP 8.4 · Bootstrap 5.3 · jQuery 4.0 · PostgREST · PostgreSQL · Caddy · Docker
**Scope:** Personal single-board Kanban task manager with multi-channel reminders

---

## 1. Architecture

### Approach
Laravel 12.x handles routing, authentication, Blade views (SPA shell only), and notification dispatch. All Kanban data flows directly between the browser and PostgREST via Caddy reverse proxy (no Laravel controller touches task data). Laravel's notification system dispatches reminders to one or more configurable channels.

### Docker Stack

```yaml
# docker-compose.yml services
app:        php:8.4-fpm — Laravel application
db:         postgres:17  — primary data store
postgrest:  postgrest/postgrest:latest — REST API over PostgreSQL
caddy:      caddy:latest — reverse proxy + TLS
```

**Caddy routing:**
- `/` and all web routes → PHP-FPM (Laravel)
- `/api/*` → PostgREST (path prefix stripped) — browser AJAX calls go here; no CORS issues

### File Structure

```
phpkanmaster/
├── app/
│   ├── Console/Commands/
│   │   └── SendReminders.php        # Artisan command: queries due tasks, dispatches notifications
│   ├── Http/Controllers/
│   │   └── KanbanController.php     # Serves SPA shell; injects PostgREST URL as JS var
│   ├── Models/
│   │   └── Task.php                 # Eloquent model used only by SendReminders command
│   └── Notifications/
│       └── TaskReminder.php         # Multi-channel notification (Pushover/Twilio/RocketChat)
├── config/
│   ├── notifications.php            # Per-channel enable/disable flags
│   └── services.php                 # Pushover token, Twilio creds, RocketChat URL/token
├── public/
│   ├── index.php                    # Laravel entry point
│   └── assets/
│       ├── css/app.css
│       └── js/app.js                # All jQuery 4.0 Kanban logic
├── resources/views/
│   ├── kanban.blade.php             # SPA shell (injects POSTGREST_URL)
│   └── auth/login.blade.php        # Login form
├── routes/
│   ├── web.php                      # GET /, POST /login, GET /logout
│   └── console.php                  # Scheduled: reminders:send every minute
├── docker/
│   ├── php/Dockerfile               # PHP 8.4-FPM + Composer + extensions
│   ├── caddy/Caddyfile              # Reverse proxy rules
│   └── postgrest/postgrest.conf     # PostgREST config (DB URL, anon role, schema)
├── docker-compose.yml
└── .env                             # All credentials and feature flags
```

### Routing (routes/web.php)

| Request | Action |
|---------|--------|
| `GET /` | Auth middleware → render `kanban.blade.php` (SPA shell) |
| `GET /login` | Show login form |
| `POST /login` | Validate credentials → set session → redirect to `/` |
| `GET /logout` | Invalidate session → redirect to `/login` |

### Auth Model
- Single-user: username + bcrypt hash stored in `.env` (`APP_USER` / `APP_PASSWORD_HASH`)
- Laravel session auth with a simple custom guard — no database users table needed
- Caddy handles TLS; PostgREST is not directly exposed (port not published to host)

---

## 2. Docker Configuration

### docker-compose.yml (outline)

```yaml
services:
  app:
    build: ./docker/php
    volumes:
      - .:/var/www/html
    depends_on: [db, postgrest]

  db:
    image: postgres:17
    environment:
      POSTGRES_DB: kanban
      POSTGRES_USER: kanban
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - pgdata:/var/lib/postgresql/data

  postgrest:
    image: postgrest/postgrest:latest
    environment:
      PGRST_DB_URI: postgres://anon:${ANON_PASSWORD}@db:5432/kanban
      PGRST_DB_SCHEMA: public
      PGRST_DB_ANON_ROLE: anon
      PGRST_SERVER_PORT: 3000
    depends_on: [db]
    # No ports exposed to host — internal only

  caddy:
    image: caddy:latest
    ports: ["80:80", "443:443"]
    volumes:
      - ./docker/caddy/Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data
    depends_on: [app, postgrest]
```

### Caddyfile (outline)

```
yourdomain.com {
    # PostgREST API — strip /api prefix before forwarding
    handle /api/* {
        uri strip_prefix /api
        reverse_proxy postgrest:3000
    }

    # Laravel app
    handle {
        root * /var/www/html/public
        php_fastcgi app:9000
        file_server
    }
}
```

### docker/php/Dockerfile (outline)

```dockerfile
FROM php:8.4-fpm
RUN apt-get update && apt-get install -y libpq-dev zip unzip \
    && docker-php-ext-install pdo pdo_pgsql
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
```

---

## 3. Database Schema

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
  id                uuid primary key default gen_random_uuid(),
  title             text not null,
  description       text,                    -- HTML from Summernote WYSIWYG
  due_date          date,
  priority          text not null default 'medium'
                      check (priority in ('low', 'medium', 'high')),
  task_column       text not null default 'new'
                      check (task_column in ('new', 'in_progress', 'review', 'on_hold', 'done')),
  position          integer not null default 0,
  category_id       uuid references categories(id) on delete set null,
  parent_id         uuid references tasks(id) on delete cascade,  -- unlimited nesting
  reminder_at       timestamptz,
  reminder_sent     boolean not null default false,
  pushover_priority integer not null default 0
                      check (pushover_priority between -2 and 2),
  pushover_retry    integer default 30,      -- seconds; required when pushover_priority = 2
  pushover_expire   integer default 3600,    -- seconds; required when pushover_priority = 2
  created_at        timestamptz not null default now(),
  updated_at        timestamptz not null default now()
);

-- Task file attachments (images/files from WYSIWYG)
create table task_files (
  id         uuid primary key default gen_random_uuid(),
  task_id    uuid references tasks(id) on delete cascade,
  filename   text not null,
  mime_type  text not null,
  data       text not null,    -- base64-encoded
  created_at timestamptz not null default now()
);

-- PostgREST anon role
create role anon nologin;
grant select, insert, update, delete on tasks, categories, task_files to anon;

-- Separate login role for PostgREST connection string
create role kanban_postgrest login password 'CHANGEME';
grant anon to kanban_postgrest;
```

### PostgREST Endpoints (via `/api/*` through Caddy)

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/tasks?order=task_column,position` | Load all tasks on boot |
| `GET` | `/api/categories?order=name` | Load categories on boot |
| `POST` | `/api/tasks` | Create task |
| `PATCH` | `/api/tasks?id=eq.{id}` | Update task (edit, column change, reorder) |
| `DELETE` | `/api/tasks?id=eq.{id}` | Delete task |
| `POST` | `/api/task_files` | Upload file/image from WYSIWYG |
| `DELETE` | `/api/task_files?id=eq.{id}` | Remove attachment |
| `GET` | `/api/categories` | Category list |
| `POST` | `/api/categories` | Create category |
| `PATCH` | `/api/categories?id=eq.{id}` | Edit category |
| `DELETE` | `/api/categories?id=eq.{id}` | Delete category |

---

## 4. Multi-Channel Notifications (Laravel)

### Channel Configuration

```php
// config/notifications.php
return [
    'channels' => [
        'pushover'   => env('NOTIFY_PUSHOVER', false),
        'twilio'     => env('NOTIFY_TWILIO', false),
        'rocketchat' => env('NOTIFY_ROCKETCHAT', false),
    ],
];
```

```dotenv
# .env — enable/disable channels
NOTIFY_PUSHOVER=true
NOTIFY_TWILIO=false
NOTIFY_ROCKETCHAT=false

# Pushover
PUSHOVER_TOKEN=your_app_token

# Twilio
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM=+15550000000

# RocketChat
ROCKETCHAT_URL=https://your.rocketchat.server.com
ROCKETCHAT_TOKEN=your_webhook_token
ROCKETCHAT_CHANNEL=#general
```

```php
// config/services.php (additions)
'pushover'   => ['token' => env('PUSHOVER_TOKEN')],
'rocketchat' => [
    'url'     => env('ROCKETCHAT_URL'),
    'token'   => env('ROCKETCHAT_TOKEN'),
    'channel' => env('ROCKETCHAT_CHANNEL'),
],
```

### TaskReminder Notification Class

```php
// app/Notifications/TaskReminder.php
use NotificationChannels\Pushover\PushoverChannel;
use NotificationChannels\Pushover\PushoverMessage;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;
use NotificationChannels\RocketChat\RocketChatWebhookChannel;
use NotificationChannels\RocketChat\RocketChatMessage;

class TaskReminder extends Notification
{
    public function __construct(private Task $task) {}

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
                $m->retry($this->task->pushover_retry)
                  ->expireAfter($this->task->pushover_expire)
            );
    }

    public function toTwilio(object $notifiable): TwilioSmsMessage
    {
        return (new TwilioSmsMessage())
            ->content("Reminder: {$this->task->title} (due {$this->task->due_date})");
    }

    public function toRocketChat(object $notifiable): RocketChatMessage
    {
        return RocketChatMessage::create(
            "📋 Reminder: *{$this->task->title}* — due {$this->task->due_date}"
        );
    }
}
```

### SendReminders Artisan Command

```php
// app/Console/Commands/SendReminders.php
class SendReminders extends Command
{
    protected $signature   = 'reminders:send';
    protected $description = 'Dispatch due task reminders via configured notification channels';

    public function handle(): void
    {
        Task::where('reminder_at', '<=', now())
            ->where('reminder_sent', false)
            ->each(function (Task $task) {
                Notification::route('pushover', config('services.pushover.user_key'))
                    ->route('twilio', config('services.twilio.to'))
                    ->route('rocketchat', null)
                    ->notify(new TaskReminder($task));
                $task->update(['reminder_sent' => true]);
            });
    }
}
```

### Scheduling

```php
// routes/console.php
Schedule::command('reminders:send')->everyMinute();
```

Cron entry on host (or in `app` container):
```
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. UI Design

### Board Layout
- Five columns side-by-side: **New · In Progress · Review · On Hold · Done**
- Desktop: all columns visible, horizontal scroll if narrow
- Mobile: columns overflow-x scroll (swipe to reveal)
- Each column shows a task count badge
- "+ Add card" shortcut at the bottom of each column

### Navbar
- App title (left)
- Category filter pills: All + one per category (client-side filter, no refetch; pills rebuilt from loaded categories array)
- "+ Add Task" button (right)
- "⚙ Categories" link (right) — opens category management modal
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
- **Category** (select, populated from `/api/categories`)
- **Due Date** (Flatpickr date picker)
- **Description** — Summernote 0.9 WYSIWYG editor, Bootstrap 5 theme
  - Toolbar: Bold, Italic, Underline, Strikethrough, lists, link, image insert, file attach, code block
  - Images/files dragged in are uploaded to `/api/task_files`, embedded as URLs
  - All input/editor text is white (`#ffffff`) on dark background

**Pushover Reminder section:**
- Remind at (Flatpickr datetime)
- Pushover Priority (select with human-readable labels):
  - Lowest (-2) — silent, badge only
  - Low (-1) — silent popup
  - Normal (0) — sound + vibration *(default)*
  - High (1) — bypass quiet hours
  - Emergency (2) — repeat until acknowledged
- Retry every (seconds) — shown + enabled only when Emergency selected, default 30
- Stop after (seconds) — shown + enabled only when Emergency selected, default 3600

**Subtasks section:**
- List of direct children with their current column status
- "New subtask" — opens a nested modal pre-set with `parent_id`
- "Link existing task" — client-side searchable dropdown (from loaded tasks array) → PATCH `parent_id`
- Unlink (✕) on each row → SweetAlert2 confirm

**Actions:**
- Delete task (bottom-left, red) — SweetAlert2 confirm with HTML list of all descendant titles
- Cancel / Save task (bottom-right)

### Category Management Modal
Triggered by "⚙ Categories" in navbar.

- Each row: color swatch (color picker) · name input (white text) · Save · Delete
- Delete → SweetAlert2 confirm: "Tasks in this category will become uncategorized."
- Footer: Add Category — name input + color picker + Add button
- Add/edit/delete immediately updates: filter pills, task modal category dropdown

---

## 6. JavaScript App Structure

`public/assets/js/app.js` — five plain-object modules, no build step:

```
App.Api       — fetch calls to /api/* (PostgREST via Caddy)
App.Board     — renders columns + cards; client-side filter by category
App.Modal     — open/close/populate task form; Summernote init/destroy
App.DnD       — jQuery UI Sortable; batch PATCH positions on drop
App.Alerts    — SweetAlert2 mixin definitions (Toast, Confirm)
```

### Boot Sequence
1. `App.Api.getTasks()` + `App.Api.getCategories()` fire in parallel
2. `App.Board.render(tasks, categories)` builds all 5 columns
3. `App.DnD.init()` activates Sortable on each column
4. Category filter pills bind to `App.Board.filter(categoryId)` — client-side, no refetch

### Drag-and-Drop Reorder
On Sortable `stop` event:
- PATCH moved card's `task_column` + `position`
- Batch PATCH sibling `position` values to reflect new order

### Tree Rendering
- All tasks fetched flat in one `GET /api/tasks`
- Tree built client-side: group by `parent_id`
- Board renders only root tasks (`parent_id = null`)
- Subtasks shown inside the edit modal (direct children list)
- Unlimited depth in data model; UI shows one level at a time in modal

---

## 7. Notifications & Alerts (SweetAlert2)

```javascript
App.Alerts.Toast = Swal.mixin({
  toast: true, position: 'top-end', showConfirmButton: false,
  timer: 3000, timerProgressBar: true, theme: 'dark',
  didOpen: (t) => { t.onmouseenter = Swal.stopTimer; t.onmouseleave = Swal.resumeTimer }
})

App.Alerts.Confirm = Swal.mixin({
  theme: 'dark', showCancelButton: true,
  confirmButtonColor: '#ef4444', cancelButtonColor: '#252d3d'
})
```

| Trigger | Type | Detail |
|---------|------|--------|
| Task saved / created | Toast success | "Task saved" |
| API error | Toast error | PostgREST error message |
| Delete task (no subtasks) | Confirm | "Delete task? This cannot be undone." |
| Delete task (has subtasks) | Confirm | Title + HTML list of all descendant task titles |
| Unlink subtask | Confirm | "Remove this subtask link?" |
| Logout | Confirm | "Log out?" |
| Delete category | Confirm | "Tasks will become uncategorized." |

No `alert()`, `confirm()`, or `prompt()` anywhere.

---

## 8. Error Handling

- All `App.Api` calls wrapped in `try/catch` → `App.Alerts.Toast.fire({ icon: 'error' })`
- Form validation (required title) fires before any API call
- Laravel `SendReminders` command logs failures to `storage/logs/laravel.log`

---

## 9. Dependencies

### PHP / Composer
| Package | Purpose |
|---------|---------|
| `laravel/framework` ^12.0 | Framework |
| `laravel-notification-channels/pushover` | Pushover channel |
| `laravel-notification-channels/twilio` | Twilio SMS channel |
| `laravel-notification-channels/rocket-chat` | RocketChat channel |

### Frontend (CDN or public/assets)
| Library | Version | Purpose |
|---------|---------|---------|
| Bootstrap | 5.3 | UI framework |
| jQuery | 4.0 | DOM + AJAX |
| jQuery UI | latest | Sortable drag-and-drop |
| Summernote | 0.9 | WYSIWYG description editor |
| SweetAlert2 | latest | All alerts, confirms, toasts |
| Flatpickr | latest | Date/datetime pickers |
