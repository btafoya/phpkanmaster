# Recurring Tasks & Notification Opt-Out Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement RFC 5545 recurring task spawning and per-task notification mute toggle per `docs/superpowers/specs/2026-04-08-kanban-recurring-tasks.md`.

**Architecture:** A new `SendReminders` Artisan command (already scheduled every minute in `routes/console.php`) checks due tasks, skips the notification if `disable_notifications` is set, and spawns the next task occurrence by parsing the task's `recurrence_rules` row via `rlanvin/php-rrule`. The frontend adds `App.Recurrence` (bell toggle + RRULE builder), a mute icon on each task card, and a collapsible recurrence section in the Add/Edit Task modal; all recurrence CRUD goes directly to PostgREST at `/api/recurrence_rules`.

**Tech Stack:** PHP 8.x, Laravel 11, `rlanvin/php-rrule`, PostgreSQL (production via `docker/db/init/`), SQLite in-memory (tests via Laravel migrations), Bootstrap 5.3, jQuery, SweetAlert2.

---

## File Map

| Action | Path | Responsibility |
|---|---|---|
| Create | `database/migrations/2026_04_09_090000_create_tasks_table.php` | Base `tasks` table for SQLite test DB |
| Create | `database/migrations/2026_04_09_100000_add_disable_notifications_to_tasks.php` | Add `disable_notifications` column |
| Create | `database/migrations/2026_04_09_100001_create_recurrence_rules_table.php` | `recurrence_rules` table |
| Modify | `docker/db/init/02-schema.sql` | Add column + table to fresh-install schema |
| Create | `docker/db/init/04-schema-updates.sql` | ALTER statements for existing Docker DB |
| Modify | `docker/db/init/03-permissions.sql` | Grant CRUD on `recurrence_rules` to anon |
| Create | `app/Models/Task.php` | Eloquent model used by `SendReminders` |
| Create | `app/Models/RecurrenceRule.php` | Eloquent model for recurrence rules |
| Create | `app/Notifications/TaskReminder.php` | Notification stub (log channel) |
| Create | `app/Console/Commands/SendReminders.php` | Artisan command: send + spawn |
| Create | `tests/Feature/SendRemindersTest.php` | Feature tests for the command |
| Modify | `public/assets/js/app.js` | `App.Api` extensions, `App.Recurrence`, bell icon on cards |
| Modify | `resources/views/kanban.blade.php` | Recurrence HTML section inside `#taskForm` |

---

## Task 1: Install php-rrule dependency

**Files:** `composer.json`, `composer.lock`, `vendor/`

- [ ] **Step 1: Install the package**

```bash
docker compose exec app composer require rlanvin/php-rrule
```

Expected: `./composer.json has been updated` with no errors.

- [ ] **Step 2: Verify autoload**

```bash
docker compose exec app php -r "require 'vendor/autoload.php'; echo class_exists('RRule\RRule') ? 'OK' : 'FAIL';"
```

Expected output: `OK`

- [ ] **Step 3: Run existing tests to confirm nothing broke**

```bash
docker compose exec app php artisan test
```

Expected: all tests pass (2 tests currently).

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat: add rlanvin/php-rrule for RFC 5545 recurrence support"
```

---

## Task 2: Base tasks migration (for test SQLite) + disable_notifications column

**Files:**
- Create: `database/migrations/2026_04_09_090000_create_tasks_table.php`
- Create: `database/migrations/2026_04_09_100000_add_disable_notifications_to_tasks.php`
- Modify: `docker/db/init/02-schema.sql`
- Create: `docker/db/init/04-schema-updates.sql`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SendRemindersTest.php` with just enough to confirm the tasks table exists in SQLite:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SendRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_table_has_disable_notifications_column(): void
    {
        $this->assertTrue(Schema::hasColumn('tasks', 'disable_notifications'));
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
docker compose exec app php artisan test --filter test_tasks_table_has_disable_notifications_column
```

Expected: FAIL — `tasks` table does not exist.

- [ ] **Step 3: Create base tasks migration**

```php
<?php
// database/migrations/2026_04_09_090000_create_tasks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            return;
        }

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->string('task_column', 20)->default('new');
            $table->integer('position')->default(0);
            $table->uuid('category_id')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->timestampTz('reminder_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->integer('pushover_priority')->default(0);
            $table->integer('pushover_retry')->nullable();
            $table->integer('pushover_expire')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
```

- [ ] **Step 4: Create disable_notifications migration**

```php
<?php
// database/migrations/2026_04_09_100000_add_disable_notifications_to_tasks.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('tasks', 'disable_notifications')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('disable_notifications')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('disable_notifications');
        });
    }
};
```

- [ ] **Step 5: Update docker/db/init/02-schema.sql — add column to tasks CREATE TABLE**

In `docker/db/init/02-schema.sql`, add `disable_notifications` to the `tasks` table definition after `pushover_expire`:

```sql
  pushover_expire   integer default 3600,
  disable_notifications boolean not null default false,
  created_at        timestamptz not null default now(),
```

- [ ] **Step 6: Create docker/db/init/04-schema-updates.sql for existing Docker DBs**

```sql
-- docker/db/init/04-schema-updates.sql
-- Run this against existing databases that were created before these schema additions.
-- Safe to run multiple times (uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).

alter table tasks
  add column if not exists disable_notifications boolean not null default false;
```

- [ ] **Step 7: Run the test to confirm it passes**

```bash
docker compose exec app php artisan test --filter test_tasks_table_has_disable_notifications_column
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_04_09_090000_create_tasks_table.php \
        database/migrations/2026_04_09_100000_add_disable_notifications_to_tasks.php \
        docker/db/init/02-schema.sql \
        docker/db/init/04-schema-updates.sql
git commit -m "feat: add disable_notifications column to tasks"
```

---

## Task 3: recurrence_rules table

**Files:**
- Create: `database/migrations/2026_04_09_100001_create_recurrence_rules_table.php`
- Modify: `docker/db/init/02-schema.sql`
- Modify: `docker/db/init/03-permissions.sql`
- Modify: `docker/db/init/04-schema-updates.sql`

- [ ] **Step 1: Write the failing test — add to SendRemindersTest.php**

Add this test method to the existing `SendRemindersTest` class:

```php
public function test_recurrence_rules_table_exists(): void
{
    $this->assertTrue(Schema::hasTable('recurrence_rules'));
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
docker compose exec app php artisan test --filter test_recurrence_rules_table_exists
```

Expected: FAIL — `recurrence_rules` table does not exist.

- [ ] **Step 3: Create recurrence_rules migration**

```php
<?php
// database/migrations/2026_04_09_100001_create_recurrence_rules_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('recurrence_rules')) {
            return;
        }

        Schema::create('recurrence_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->text('rrule');
            $table->timestampTz('next_occurrence_at');
            $table->date('end_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrence_rules');
    }
};
```

- [ ] **Step 4: Update docker/db/init/02-schema.sql — add recurrence_rules table after task_files**

```sql
-- Recurrence rules
create table recurrence_rules (
  id                  uuid primary key default gen_random_uuid(),
  task_id             uuid references tasks(id) on delete cascade,
  rrule               text not null,
  next_occurrence_at  timestamptz not null,
  end_date            date,
  active              boolean not null default true,
  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now()
);

create index idx_recurrence_rules_active_next on recurrence_rules (active, next_occurrence_at);
```

- [ ] **Step 5: Update docker/db/init/03-permissions.sql — add recurrence_rules**

Replace the existing grant line with:

```sql
grant select, insert, update, delete on tasks, categories, task_files, recurrence_rules to anon;
```

- [ ] **Step 6: Add to docker/db/init/04-schema-updates.sql**

Append to the file:

```sql

create table if not exists recurrence_rules (
  id                  uuid primary key default gen_random_uuid(),
  task_id             uuid references tasks(id) on delete cascade,
  rrule               text not null,
  next_occurrence_at  timestamptz not null,
  end_date            date,
  active              boolean not null default true,
  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now()
);

create index if not exists idx_recurrence_rules_active_next
  on recurrence_rules (active, next_occurrence_at);

grant select, insert, update, delete on recurrence_rules to anon;
```

- [ ] **Step 7: Run the tests to confirm they pass**

```bash
docker compose exec app php artisan test --filter SendReminders
```

Expected: 2 tests pass.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_04_09_100001_create_recurrence_rules_table.php \
        docker/db/init/02-schema.sql \
        docker/db/init/03-permissions.sql \
        docker/db/init/04-schema-updates.sql
git commit -m "feat: add recurrence_rules table and permissions"
```

---

## Task 4: Task and RecurrenceRule Eloquent models

**Files:**
- Create: `app/Models/Task.php`
- Create: `app/Models/RecurrenceRule.php`

- [ ] **Step 1: Create app/Models/Task.php**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    protected $table = 'tasks';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'title', 'description', 'due_date', 'priority', 'task_column',
        'position', 'category_id', 'parent_id', 'reminder_at', 'reminder_sent',
        'pushover_priority', 'pushover_retry', 'pushover_expire',
        'disable_notifications',
    ];

    protected $casts = [
        'disable_notifications' => 'boolean',
        'reminder_sent'         => 'boolean',
        'reminder_at'           => 'datetime',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function recurrenceRule(): HasOne
    {
        return $this->hasOne(RecurrenceRule::class, 'task_id');
    }
}
```

- [ ] **Step 2: Create app/Models/RecurrenceRule.php**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurrenceRule extends Model
{
    protected $table = 'recurrence_rules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'task_id', 'rrule', 'next_occurrence_at', 'end_date', 'active',
    ];

    protected $casts = [
        'active'             => 'boolean',
        'next_occurrence_at' => 'datetime',
        'end_date'           => 'date',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
```

- [ ] **Step 3: Run PHPStan**

```bash
docker compose exec app /usr/bin/php vendor/bin/phpstan analyse app --memory-limit=256M
```

Expected: no errors.

- [ ] **Step 4: Run tests**

```bash
docker compose exec app php artisan test
```

Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Task.php app/Models/RecurrenceRule.php
git commit -m "feat: add Task and RecurrenceRule Eloquent models"
```

---

## Task 5: TaskReminder notification stub

**Files:**
- Create: `app/Notifications/TaskReminder.php`

The full multi-channel (Pushover/Twilio/RocketChat) implementation is a separate concern. This stub uses the built-in `log` channel so the command is testable today.

- [ ] **Step 1: Create app/Notifications/TaskReminder.php**

```php
<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskReminder extends Notification
{
    use Queueable;

    public function __construct(public readonly Task $task) {}

    public function via(object $notifiable): array
    {
        return ['log'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id'     => $this->task->id,
            'title'       => $this->task->title,
            'reminder_at' => $this->task->reminder_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 2: Run PHPStan**

```bash
docker compose exec app /usr/bin/php vendor/bin/phpstan analyse app --memory-limit=256M
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/TaskReminder.php
git commit -m "feat: add TaskReminder notification stub (log channel)"
```

---

## Task 6: SendReminders Artisan command

**Files:**
- Create: `app/Console/Commands/SendReminders.php`

- [ ] **Step 1: Write the failing test — replace SendRemindersTest.php**

Replace the entire contents of `tests/Feature/SendRemindersTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\RecurrenceRule;
use App\Models\Task;
use App\Notifications\TaskReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SendRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_table_has_disable_notifications_column(): void
    {
        $this->assertTrue(Schema::hasColumn('tasks', 'disable_notifications'));
    }

    public function test_recurrence_rules_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('recurrence_rules'));
    }

    public function test_sends_notification_for_due_task(): void
    {
        Notification::fake();
        $this->makeTask();

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentOnDemand(TaskReminder::class);
    }

    public function test_skips_notification_when_disable_notifications_is_true(): void
    {
        Notification::fake();
        $this->makeTask(['disable_notifications' => true]);

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_marks_task_reminder_sent(): void
    {
        Notification::fake();
        $task = $this->makeTask();

        $this->artisan('reminders:send')->assertSuccessful();

        $this->assertTrue(Task::find($task->id)->reminder_sent);
    }

    public function test_does_not_send_reminder_for_future_task(): void
    {
        Notification::fake();
        $this->makeTask(['reminder_at' => now()->addHour()]);

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_spawns_next_task_for_recurring_rule(): void
    {
        Notification::fake();
        $dtstart = now()->subDay()->format('Ymd\THis\Z');
        $task = $this->makeTask(['reminder_at' => now()->subDay()]);

        $rule = new RecurrenceRule([
            'id'                 => (string) Str::uuid(),
            'task_id'            => $task->id,
            'rrule'              => json_encode(['FREQ' => 'DAILY', 'DTSTART' => $dtstart]),
            'next_occurrence_at' => now()->addDay(),
            'active'             => true,
        ]);
        $rule->save();

        $this->artisan('reminders:send')->assertSuccessful();

        $this->assertDatabaseCount('tasks', 2);
        $newTask = Task::where('reminder_sent', false)->first();
        $this->assertNotNull($newTask);
        $this->assertTrue($newTask->reminder_at->isAfter($task->reminder_at));
    }

    public function test_deactivates_rule_when_no_next_occurrence(): void
    {
        Notification::fake();
        $yesterday = now()->subDay()->format('Ymd\THis\Z');
        $task = $this->makeTask(['reminder_at' => now()->subDay()]);

        // UNTIL is before now, so no next occurrence exists
        $rule = new RecurrenceRule([
            'id'                 => (string) Str::uuid(),
            'task_id'            => $task->id,
            'rrule'              => json_encode([
                'FREQ'    => 'DAILY',
                'DTSTART' => now()->subDays(3)->format('Ymd\THis\Z'),
                'UNTIL'   => $yesterday,
            ]),
            'next_occurrence_at' => now()->addDay(),
            'active'             => true,
        ]);
        $rule->save();

        $this->artisan('reminders:send')->assertSuccessful();

        $this->assertDatabaseCount('tasks', 1);
        $this->assertFalse(RecurrenceRule::find($rule->id)->active);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function makeTask(array $overrides = []): Task
    {
        $task = new Task(array_merge([
            'id'                    => (string) Str::uuid(),
            'title'                 => 'Test task',
            'priority'              => 'medium',
            'task_column'           => 'new',
            'position'              => 0,
            'reminder_at'           => now()->subMinute(),
            'reminder_sent'         => false,
            'disable_notifications' => false,
            'pushover_priority'     => 0,
        ], $overrides));
        $task->save();
        return $task;
    }
}
```

- [ ] **Step 2: Run the new tests to confirm they fail**

```bash
docker compose exec app php artisan test --filter SendRemindersTest
```

Expected: 4 new tests fail with `The [reminders:send] command does not exist.`

- [ ] **Step 3: Create app/Console/Commands/SendReminders.php**

```php
<?php

namespace App\Console\Commands;

use App\Models\RecurrenceRule;
use App\Models\Task;
use App\Notifications\TaskReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RRule\RRule;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Send task reminders and spawn next recurring task instances';

    public function handle(): int
    {
        Task::where('reminder_at', '<=', now())
            ->where('reminder_sent', false)
            ->each(function (Task $task): void {
                try {
                    if (!$task->disable_notifications) {
                        Notification::route('log', null)->notify(new TaskReminder($task));
                    }

                    $this->handleRecurrence($task);

                    $task->update(['reminder_sent' => true]);
                } catch (\Throwable $e) {
                    Log::error('SendReminders: failed for task', [
                        'task_id' => $task->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            });

        return Command::SUCCESS;
    }

    private function handleRecurrence(Task $task): void
    {
        $rule = RecurrenceRule::where('task_id', $task->id)
            ->where('active', true)
            ->first();

        if (!$rule) {
            return;
        }

        if ($rule->end_date && $rule->end_date->lt(now())) {
            $rule->update(['active' => false]);
            return;
        }

        $rrule = new RRule(json_decode($rule->rrule, true));
        $nextOccurrence = $rrule->getNthOccurrenceAfter($task->reminder_at);

        if (!$nextOccurrence) {
            $rule->update(['active' => false]);
            return;
        }

        $newTask = $task->replicate();
        $newTask->id = (string) Str::uuid();
        $newTask->reminder_at = $nextOccurrence;
        $newTask->reminder_sent = false;
        $newTask->save();

        $task->children()->each(function (Task $subtask) use ($newTask): void {
            $newSubtask = $subtask->replicate();
            $newSubtask->id = (string) Str::uuid();
            $newSubtask->parent_id = $newTask->id;
            $newSubtask->save();
        });

        $nextAfter = $rrule->getNthOccurrenceAfter($nextOccurrence);
        $rule->update([
            'next_occurrence_at' => $nextAfter
                ? $nextAfter->format('Y-m-d H:i:s')
                : $rule->next_occurrence_at,
        ]);
    }
}
```

- [ ] **Step 4: Run the tests to confirm they pass**

```bash
docker compose exec app php artisan test --filter SendRemindersTest
```

Expected: all 7 tests pass.

- [ ] **Step 5: Run PHPStan**

```bash
docker compose exec app /usr/bin/php vendor/bin/phpstan analyse app --memory-limit=256M
```

Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/SendReminders.php \
        app/Notifications/TaskReminder.php \
        tests/Feature/SendRemindersTest.php
git commit -m "feat: implement SendReminders command with recurrence spawning"
```

---

## Task 7: Frontend — App.Api extensions + App.Recurrence + bell icon on cards

**Files:**
- Modify: `public/assets/js/app.js`

This task adds three things to `app.js`:
1. Four new methods on `App.Api` for recurrence_rules CRUD
2. The `App.Recurrence` module (bell toggle + RRULE builder)
3. Bell icon in `createTaskCard` + event delegation handler
4. Updated `getTasks()` to embed recurrence rule existence

- [ ] **Step 1: Update App.Api.getTasks() to embed recurrence rules**

Find this line in `app.js`:
```javascript
        async getTasks() {
            return this.request('/tasks?select=*&order=task_column.asc,position.asc');
        },
```

Replace with:
```javascript
        async getTasks() {
            return this.request('/tasks?select=*,recurrence_rules(id,active)&order=task_column.asc,position.asc');
        },
```

This returns `task.recurrence_rules` as an array on each task object.

- [ ] **Step 2: Add recurrence_rules API methods to App.Api**

After the `deleteFile` method (before the closing `},` of `App.Api`), add:

```javascript
        async getRecurrenceRuleForTask(taskId) {
            const results = await this.request(`/recurrence_rules?task_id=eq.${taskId}&limit=1`);
            return results[0] || null;
        },

        async createRecurrenceRule(data) {
            return this.request('/recurrence_rules', {
                method: 'POST',
                body: JSON.stringify(data),
            });
        },

        async updateRecurrenceRule(id, data) {
            return this.request(`/recurrence_rules?id=eq.${id}`, {
                method: 'PATCH',
                body: JSON.stringify(data),
            });
        },

        async deleteRecurrenceRule(id) {
            return this.request(`/recurrence_rules?id=eq.${id}`, {
                method: 'DELETE',
            });
        },
```

- [ ] **Step 3: Add App.Recurrence module**

After `App.Alerts = { ... };` and before the `$(async function() {` block, add:

```javascript
App.Recurrence = {
    buildRRule(formData) {
        const freqMap = {
            daily:          'DAILY',
            every_other_day:'DAILY',
            weekly:         'WEEKLY',
            monthly:        'MONTHLY',
            yearly:         'YEARLY',
        };

        const dtstart = formData.reminder_at
            ? new Date(formData.reminder_at).toISOString().replace(/[-:.]/g, '').slice(0, 15) + 'Z'
            : new Date().toISOString().replace(/[-:.]/g, '').slice(0, 15) + 'Z';

        const rrule = {
            FREQ:    freqMap[formData.pattern],
            DTSTART: dtstart,
        };

        if (formData.pattern === 'every_other_day') {
            rrule.INTERVAL = 2;
        } else if (formData.interval > 1) {
            rrule.INTERVAL = formData.interval;
        }

        if (formData.pattern === 'weekly' && formData.weekDays.length > 0) {
            rrule.BYDAY = formData.weekDays.join(',');
        }

        if (formData.endType === 'on_date' && formData.endDate) {
            rrule.UNTIL = new Date(formData.endDate).toISOString().replace(/[-:.]/g, '').slice(0, 15) + 'Z';
        }

        return JSON.stringify(rrule);
    },

    humanReadable(rruleJson) {
        try {
            const r = JSON.parse(rruleJson);
            const interval = r.INTERVAL || 1;
            const freqLabel = { DAILY: 'day', WEEKLY: 'week', MONTHLY: 'month', YEARLY: 'year' };
            const unit = freqLabel[r.FREQ] || r.FREQ.toLowerCase();
            const days = r.BYDAY ? ` on ${r.BYDAY}` : '';
            const until = r.UNTIL ? ` until ${r.UNTIL.slice(0, 8)}` : '';
            return `Every ${interval > 1 ? interval + ' ' : ''}${unit}${interval > 1 ? 's' : ''}${days}${until}`;
        } catch {
            return 'Custom recurrence';
        }
    },

    async toggleNotifications(taskId, currentMuted) {
        const newMuted = !currentMuted;
        const result = await App.Alerts.Confirm.fire({
            title: newMuted ? 'Mute notifications for this task?' : 'Enable notifications for this task?',
            icon:  'question',
        });
        if (!result.isConfirmed) return;

        try {
            await App.Api.request(`/tasks?id=eq.${taskId}`, {
                method: 'PATCH',
                body: JSON.stringify({ disable_notifications: newMuted }),
            });
            App.Alerts.Toast.fire({
                icon:  'success',
                title: newMuted ? 'Notifications muted' : 'Notifications enabled',
            });
            this.updateBellIcon(taskId, newMuted);
        } catch {
            App.Alerts.Toast.fire({ icon: 'error', title: 'Failed to update notifications' });
        }
    },

    updateBellIcon(taskId, muted) {
        const btn = document.querySelector(`.bell-icon[data-id="${taskId}"]`);
        if (!btn) return;
        btn.textContent = muted ? '🔕' : '🔔';
        btn.dataset.muted = String(muted);
        btn.title = muted ? 'Notifications muted (click to enable)' : 'Notifications enabled (click to mute)';
    },
};
```

- [ ] **Step 4: Add bell icon + recurrence badge to createTaskCard**

Find this block in `createTaskCard`:
```javascript
        const dueDate = task.due_date ? `<div class="small text-muted"><i class="far fa-calendar-alt"></i> ${task.due_date}</div>` : '';
        const reminderIcon = task.reminder_at ? `<span class="text-warning ms-2" title="Reminder: ${task.reminder_at}"><i class="fas fa-bell"></i></span>` : '';
```

Replace with:
```javascript
        const dueDate = task.due_date ? `<div class="small text-muted"><i class="far fa-calendar-alt"></i> ${task.due_date}</div>` : '';
        const reminderIcon = task.reminder_at ? `<span class="text-warning ms-2" title="Reminder: ${task.reminder_at}"><i class="fas fa-bell"></i></span>` : '';

        const hasActiveRule = task.recurrence_rules?.some(r => r.active);
        const recurrenceBadge = hasActiveRule ? `<span class="badge bg-secondary ms-1" title="Recurring task" style="font-size:0.65rem">🔁</span>` : '';

        const bellIcon = task.reminder_at
            ? `<button type="button"
                 class="btn btn-link btn-sm p-0 ms-1 bell-icon"
                 data-action="toggle-bell"
                 data-id="${task.id}"
                 data-muted="${task.disable_notifications}"
                 title="${task.disable_notifications ? 'Notifications muted (click to enable)' : 'Notifications enabled (click to mute)'}"
               >${task.disable_notifications ? '🔕' : '🔔'}</button>`
            : '';
```

Then find the line where `reminderIcon` is rendered in the card HTML:
```javascript
                        <div class="d-flex align-items-center gap-1">
                            ${categoryBadge}
                            ${reminderIcon}
                        </div>
```

Replace with:
```javascript
                        <div class="d-flex align-items-center gap-1">
                            ${categoryBadge}
                            ${reminderIcon}
                            ${bellIcon}
                            ${recurrenceBadge}
                        </div>
```

- [ ] **Step 5: Add bell toggle event handler**

After the existing `$(document).on('click', '[data-action="delete"]', ...)` block, add:

```javascript
$(document).on('click', '[data-action="toggle-bell"]', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const taskId = $(this).data('id');
    const muted  = $(this).data('muted') === true || $(this).data('muted') === 'true';
    App.Recurrence.toggleNotifications(taskId, muted);
});
```

- [ ] **Step 6: Reload the app and manually verify bell icon appears on cards with reminders**

```bash
# App is at http://localhost:8181/
# Open a task with a reminder_at set — bell icon (🔔) should appear next to the reminder icon.
# Click the bell — SweetAlert2 confirm appears → confirm → icon changes to 🔕, toast shows "Notifications muted".
# Refresh the page — icon remains 🔕 (persisted to DB via PostgREST).
```

- [ ] **Step 7: Run full test suite**

```bash
docker compose exec app php artisan test
```

Expected: all tests pass.

- [ ] **Step 8: Commit**

```bash
git add public/assets/js/app.js
git commit -m "feat: add App.Recurrence module and bell icon toggle on task cards"
```

---

## Task 8: Frontend — recurrence section in Add/Edit Task modal

**Files:**
- Modify: `resources/views/kanban.blade.php`
- Modify: `public/assets/js/app.js`

- [ ] **Step 1: Add recurrence HTML section to kanban.blade.php**

Find the closing `</form>` tag of `#taskForm` (line ~185, after the Pushover section):
```html
                        </div>
                    </form>
```

Insert the recurrence section immediately before `</form>`:

```html
                        <div class="mb-3 p-3 bg-dark border border-secondary rounded">
                            <div class="d-flex align-items-center mb-2">
                                <input class="form-check-input me-2" type="checkbox" id="repeatTask">
                                <label class="form-check-label fw-semibold" for="repeatTask">&#x1F501; Repeat this task</label>
                            </div>
                            <div id="recurrenceFields" style="display:none">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="small">Repeat</label>
                                        <select id="recurrencePattern" class="form-select form-select-sm bg-dark text-light border-secondary">
                                            <option value="daily">Daily</option>
                                            <option value="every_other_day">Every other day</option>
                                            <option value="weekly" selected>Weekly</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small">Every <span id="intervalLabel">week(s)</span></label>
                                        <input type="number" id="recurrenceInterval" class="form-control form-control-sm bg-dark text-light border-secondary" value="1" min="1" max="99">
                                    </div>
                                </div>
                                <div id="weekdaySelector" class="mb-2">
                                    <label class="small d-block mb-1">On these days:</label>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn" data-day="MO">M</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn" data-day="TU">T</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn" data-day="WE">W</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn" data-day="TH">T</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn" data-day="FR">F</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn" data-day="SA">S</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn" data-day="SU">S</button>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="small d-block mb-1">End</label>
                                    <div class="d-flex gap-3 align-items-center flex-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recurrenceEnd" id="endNever" value="never" checked>
                                            <label class="form-check-label small" for="endNever">Never</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recurrenceEnd" id="endOnDate" value="on_date">
                                            <label class="form-check-label small" for="endOnDate">On date</label>
                                        </div>
                                        <input type="text" id="recurrenceEndDate" class="form-control form-control-sm bg-dark text-light border-secondary datepicker" style="display:none; width:auto">
                                    </div>
                                </div>
                                <div id="recurrencePreview" class="small text-info p-2 border border-secondary rounded" style="display:none"></div>
                            </div>
                        </div>
```

- [ ] **Step 2: Update App.Modal.Task in app.js — add recurrence fields to open()**

In `App.Modal.Task.open()`, after the block that populates task fields when editing (`if (taskId) { ... }`), find the line:

```javascript
            new bootstrap.Modal(document.getElementById('taskModal')).show();
```

Insert before it:

```javascript
            // Reset recurrence section
            App.Recurrence._currentRuleId = null;
            $('#repeatTask').prop('checked', false);
            $('#recurrenceFields').hide();
            $('#recurrencePattern').val('weekly');
            $('#recurrenceInterval').val(1);
            $('.weekday-btn').removeClass('active btn-secondary').addClass('btn-outline-secondary');
            $('#endNever').prop('checked', true);
            $('#recurrenceEndDate').hide().val('');
            $('#recurrencePreview').hide().text('');

            if (taskId) {
                const rule = await App.Api.getRecurrenceRuleForTask(taskId);
                if (rule && rule.active) {
                    App.Recurrence._currentRuleId = rule.id;
                    App.Recurrence._loadRule(rule);
                }
            }
```

- [ ] **Step 3: Add _loadRule helper and modal bindings to App.Recurrence**

After `updateBellIcon` inside `App.Recurrence`, add:

```javascript
    _currentRuleId: null,

    _loadRule(rule) {
        const r = JSON.parse(rule.rrule);
        const patternMap = { DAILY: r.INTERVAL === 2 ? 'every_other_day' : 'daily', WEEKLY: 'weekly', MONTHLY: 'monthly', YEARLY: 'yearly' };
        const pattern = patternMap[r.FREQ] || 'weekly';

        $('#repeatTask').prop('checked', true);
        $('#recurrenceFields').show();
        $('#recurrencePattern').val(pattern);
        $('#recurrenceInterval').val(r.INTERVAL && r.INTERVAL !== 2 ? r.INTERVAL : 1);

        if (r.BYDAY) {
            r.BYDAY.split(',').forEach(day => {
                $(`.weekday-btn[data-day="${day}"]`).removeClass('btn-outline-secondary').addClass('btn-secondary active');
            });
        }

        if (r.UNTIL) {
            $('#endOnDate').prop('checked', true);
            $('#recurrenceEndDate').show().val(r.UNTIL.slice(0, 8));
        }

        this._updatePreview();
    },

    _getFormData() {
        return {
            pattern:    $('#recurrencePattern').val(),
            interval:   parseInt($('#recurrenceInterval').val(), 10) || 1,
            weekDays:   $('.weekday-btn.active').map(function() { return $(this).data('day'); }).get(),
            endType:    $('input[name="recurrenceEnd"]:checked').val(),
            endDate:    $('#recurrenceEndDate').val(),
            reminder_at: $('[name="reminder_at"]').val(),
        };
    },

    _updatePreview() {
        const rruleJson = this.buildRRule(this._getFormData());
        const readable  = this.humanReadable(rruleJson);
        $('#recurrencePreview').text(readable).show();
    },

    initModalBindings() {
        $('#repeatTask').on('change', function() {
            $('#recurrenceFields').toggle(this.checked);
            if (this.checked) App.Recurrence._updatePreview();
        });

        $('#recurrencePattern').on('change', function() {
            const isWeekly = $(this).val() === 'weekly';
            $('#weekdaySelector').toggle(isWeekly);
            const labels = { daily: 'day(s)', every_other_day: 'day(s)', weekly: 'week(s)', monthly: 'month(s)', yearly: 'year(s)' };
            $('#intervalLabel').text(labels[$(this).val()] || 'unit(s)');
            App.Recurrence._updatePreview();
        });

        $('#recurrenceInterval').on('input', () => App.Recurrence._updatePreview());

        $(document).on('click', '.weekday-btn', function() {
            $(this).toggleClass('btn-outline-secondary btn-secondary active');
            App.Recurrence._updatePreview();
        });

        $('input[name="recurrenceEnd"]').on('change', function() {
            $('#recurrenceEndDate').toggle($(this).val() === 'on_date');
            App.Recurrence._updatePreview();
        });

        $('#recurrenceEndDate').on('change', () => App.Recurrence._updatePreview());
    },
```

- [ ] **Step 4: Call App.Recurrence.initModalBindings() in the DOMContentLoaded block**

Find this block near the bottom of `app.js`:
```javascript
    await App.Board.init();
    App.DnD.init();
```

Replace with:
```javascript
    await App.Board.init();
    App.DnD.init();
    App.Recurrence.initModalBindings();
```

- [ ] **Step 5: Update App.Modal.Task.save() to handle recurrence_rules CRUD**

In `App.Modal.Task.save()`, find the try/catch block:
```javascript
            try {
                if (data.id) {
                    await App.Api.updateTask(data.id, data);
                } else {
                    await App.Api.createTask(data);
                }
                App.Alerts.Toast.fire({ icon: 'success', title: 'Task saved' });
                bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
                await App.Board.render();
            } catch (e) {
                App.Alerts.Toast.fire({ icon: 'error', title: e.message });
            }
```

Replace with:
```javascript
            try {
                let savedTaskId = data.id;

                if (data.id) {
                    await App.Api.updateTask(data.id, data);
                } else {
                    const created = await App.Api.createTask(data);
                    savedTaskId = created[0]?.id;
                }

                // Handle recurrence rule
                const repeatEnabled = $('#repeatTask').is(':checked');
                const existingRuleId = App.Recurrence._currentRuleId;

                if (repeatEnabled && savedTaskId) {
                    const rruleJson = App.Recurrence.buildRRule({
                        ...App.Recurrence._getFormData(),
                        reminder_at: data.reminder_at,
                    });

                    if (existingRuleId) {
                        await App.Api.updateRecurrenceRule(existingRuleId, {
                            rrule:  rruleJson,
                            active: true,
                        });
                    } else {
                        // Calculate a rough next_occurrence_at for storage
                        const dtstart = data.reminder_at
                            ? new Date(data.reminder_at).toISOString()
                            : new Date().toISOString();

                        await App.Api.createRecurrenceRule({
                            task_id:            savedTaskId,
                            rrule:              rruleJson,
                            next_occurrence_at: dtstart,
                            active:             true,
                        });
                    }
                } else if (!repeatEnabled && existingRuleId) {
                    await App.Api.deleteRecurrenceRule(existingRuleId);
                }

                App.Alerts.Toast.fire({ icon: 'success', title: 'Task saved' });
                bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
                await App.Board.render();
            } catch (e) {
                App.Alerts.Toast.fire({ icon: 'error', title: e.message });
            }
```

- [ ] **Step 6: Manually test the full modal flow**

```
1. Open http://localhost:8181/ — log in
2. Click Add Task → set Title, set Reminder At (required for bell icon)
3. Check "Repeat this task" → recurrence fields appear
4. Select Weekly, pick Monday (M button), set interval to 1
5. Preview shows: "Every week on MO"
6. Save → toast "Task saved"
7. New task card shows 🔔 bell and 🔁 badge
8. Click 🔔 → SweetAlert confirm → confirm → icon changes to 🔕
9. Edit the task → recurrence section is pre-populated with the saved rule
10. Uncheck "Repeat this task" → save → 🔁 badge disappears after board refresh
```

- [ ] **Step 7: Run full test suite**

```bash
docker compose exec app php artisan test
```

Expected: all tests pass.

- [ ] **Step 8: Run PHPStan**

```bash
docker compose exec app /usr/bin/php vendor/bin/phpstan analyse app --memory-limit=256M
```

Expected: no errors.

- [ ] **Step 9: Commit**

```bash
git add resources/views/kanban.blade.php public/assets/js/app.js
git commit -m "feat: add recurrence section to task modal with live RRULE preview"
```

---

## Task 9: Apply schema updates to running Docker DB

This step applies the new column and table to the live PostgreSQL instance in Docker.

- [ ] **Step 1: Run the schema update SQL against the running Docker DB**

```bash
docker compose exec db psql -U postgres -d kanban -f /docker-entrypoint-initdb.d/04-schema-updates.sql
```

If the file isn't available inside the container, run it inline:
```bash
docker compose exec db psql -U postgres -d kanban -c "
alter table tasks add column if not exists disable_notifications boolean not null default false;

create table if not exists recurrence_rules (
  id                  uuid primary key default gen_random_uuid(),
  task_id             uuid references tasks(id) on delete cascade,
  rrule               text not null,
  next_occurrence_at  timestamptz not null,
  end_date            date,
  active              boolean not null default true,
  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now()
);

create index if not exists idx_recurrence_rules_active_next
  on recurrence_rules (active, next_occurrence_at);

grant select, insert, update, delete on recurrence_rules to anon;
"
```

- [ ] **Step 2: Verify columns exist**

```bash
docker compose exec db psql -U postgres -d kanban -c "\d tasks" | grep disable_notifications
docker compose exec db psql -U postgres -d kanban -c "\d recurrence_rules"
```

Expected: `disable_notifications` appears in tasks; `recurrence_rules` table structure is shown.

- [ ] **Step 3: Reload the app and confirm no JS errors**

Open `http://localhost:8181/` in the browser. Check the browser console — no errors expected. Task cards should load normally.

---

## Spec Coverage Checklist

| Spec Section | Covered By |
|---|---|
| `recurrence_rules` table | Tasks 3, 9 |
| `tasks.disable_notifications` column | Tasks 2, 9 |
| `rlanvin/php-rrule` dependency | Task 1 |
| `SendReminders` — skip notification when muted | Task 6 |
| `SendReminders` — spawn next occurrence | Task 6 |
| `SendReminders` — clone subtasks | Task 6 |
| `SendReminders` — update `next_occurrence_at` | Task 6 |
| `SendReminders` — deactivate rule at end | Task 6 |
| Bell icon toggle on task card (🔔/🔕) | Task 7 |
| Recurrence badge (🔁) on card | Task 7 |
| `PATCH /api/tasks` for bell toggle | Task 7 |
| Recurrence section in Add/Edit modal | Task 8 |
| Pattern dropdown, interval, weekday selector, end date | Task 8 |
| Live RRULE preview | Task 8 |
| `POST /api/recurrence_rules` on save | Task 8 |
| `PATCH /api/recurrence_rules` on edit | Task 8 |
| `DELETE /api/recurrence_rules` on uncheck | Task 8 |
| `buildRRule` JS function | Task 7 |
| Error handling (invalid rule, toggle fail) | Tasks 7, 8 |
