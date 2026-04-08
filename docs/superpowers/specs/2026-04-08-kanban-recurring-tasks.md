# phpKanMaster — Recurring Tasks & Notification Opt-Out Spec

**Date:** 2026-04-08
**Extends:** `2026-04-08-kanban-design.md`
**Scope:** RFC 5545 recurring tasks + per-task notification mute

---

## 1. Overview

Two feature additions to the Kanban design spec:

1. **Recurring Tasks** — Tasks that automatically spawn the next occurrence using RFC 5545 RRULE format (via `rlanvin/php-rrule`)
2. **Notification Opt-Out** — Per-task toggle to disable notifications (bell icon on card)

---

## 2. Database Schema Changes

### New Table: `recurrence_rules`

```sql
create table recurrence_rules (
  id                  uuid primary key default gen_random_uuid(),
  task_id             uuid references tasks(id) on delete cascade,
  rrule               text not null,           -- RFC 5545 RRULE string
  next_occurrence_at  timestamptz not null,    -- when next task should be created
  end_date            date,                    -- optional soft limit
  active              boolean not null default true,
  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now()
);

create index idx_recurrence_rules_active_next on recurrence_rules (active, next_occurrence_at);
```

### RRULE String Examples

| Pattern | RRULE String |
|---------|--------------|
| Daily | `FREQ=DAILY` |
| Every other day | `FREQ=DAILY;INTERVAL=2` |
| Weekly | `FREQ=WEEKLY` |
| Weekdays only (Mon-Fri) | `FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR` |
| Every 2 weeks on Monday | `FREQ=WEEKLY;INTERVAL=2;BYDAY=MO` |
| Monthly | `FREQ=MONTHLY` |
| First Monday of month | `FREQ=MONTHLY;BYDAY=1MO` |
| 15th of every month | `FREQ=MONTHLY;BYMONTHDAY=15` |
| Yearly | `FREQ=YEARLY` |
| Until specific date | `FREQ=DAILY;UNTIL=20261231T235959Z` |

### Modified Table: `tasks`

```sql
alter table tasks
  add column disable_notifications boolean not null default false;
```

---

## 3. Composer Dependencies

```bash
composer require rlanvin/php-rrule
```

**Usage in PHP:**

```php
use RRule\RRule;

// Parse stored RRULE
$rrule = new RRule([
    'FREQ' => 'WEEKLY',
    'INTERVAL' => 1,
    'BYDAY' => 'MO',
    'DTSTART' => '2026-04-15T09:00:00Z'
]);

// Get next occurrence after a given date
$next = $rrule->getNthOccurrenceAfter(new DateTime());

// Human-readable description
echo $rrule->humanReadable(); // "weekly on Monday"
```

---

## 4. SendReminders Command Updates

### Modified Behavior

```php
// app/Console/Commands/SendReminders.php

public function handle(): void
{
    Task::where('reminder_at', '<=', now())
        ->where('reminder_sent', false)
        ->each(function (Task $task) {
            // Check if notifications are enabled for this task
            if (!$task->disable_notifications) {
                // Dispatch multi-channel notification
                Notification::route('pushover', config('services.pushover.user_key'))
                    ->route('twilio', config('services.twilio.to'))
                    ->route('rocketchat', null)
                    ->notify(new TaskReminder($task));
            }

            // Handle recurring tasks
            $rule = RecurrenceRule::where('task_id', $task->id)
                ->where('active', true)
                ->first();

            if ($rule && (!$rule->end_date || $rule->end_date >= now())) {
                // Calculate next occurrence using php-rrule
                $rrule = new RRule(json_decode($rule->rrule, true));
                $nextOccurrence = $rrule->getNthOccurrenceAfter($task->reminder_at);

                if ($nextOccurrence) {
                    // Create new task instance with same metadata
                    $newTask = $task->replicate([
                        'id', 'reminder_at', 'reminder_sent', 'created_at', 'updated_at'
                    ]);
                    $newTask->reminder_at = $nextOccurrence;
                    $newTask->reminder_sent = false;
                    $newTask->save();

                    // Clone subtasks if any
                    $task->children()->each(function ($subtask) use ($newTask) {
                        $newSubtask = $subtask->replicate(['id', 'parent_id', 'created_at', 'updated_at']);
                        $newSubtask->parent_id = $newTask->id;
                        $newSubtask->save();
                    });

                    // Update rule's next_occurrence_at
                    $nextAfter = $rrule->getNthOccurrenceAfter($nextOccurrence);
                    $rule->update([
                        'next_occurrence_at' => $nextAfter?->format('Y-m-d H:i:s')
                    ]);
                }
            }

            // Mark original task as reminded
            $task->update(['reminder_sent' => true]);
        });
}
```

---

## 5. UI Design

### Task Card — Bell Icon Toggle

**Location:** Top-right corner of each task card

**States:**
- 🔔 (bell) — Notifications enabled (default)
- 🔕 (bell with slash) — Notifications disabled for this task

**Behavior:**
- Click triggers SweetAlert2 confirm: "Disable notifications for this task?"
- On confirm: PATCH `/api/tasks?id=eq.{id}` with `{ disable_notifications: true }`
- Toast confirms: "Notifications muted" / "Notifications enabled"

**Recurring Task Badge:**
- Small badge next to bell: `🔁 Daily` | `🔁 Weekly` | etc.
- Shows pattern from associated `recurrence_rules.rrule`

### Add/Edit Task Modal — Recurrence Section

**Expandable section** below Description, above Pushover Reminder:

```
┌─────────────────────────────────────────────────────────┐
│ 🔁 Recurrence                              ✕ collapse   │
├─────────────────────────────────────────────────────────┤
│ ☐ Repeat this task                                      │
│                                                         │
│ Repeat: [Weekly ▼]  Interval: [1] week(s)               │
│                                                         │
│ On these days:  Ⓜ  Ⓣ  Ⓦ  Ⓣ  ⓕ  Ⓢ  Ⓤ                   │
│                                                         │
│ End: ◉ Never  ○ On date [📅 picker]                     │
│                                                         │
│ ┌───────────────────────────────────────────────────┐   │
│ │ Next occurrence                                   │   │
│ │ 📅 Apr 15, 2026 at 9:00 AM                        │   │
│ │ Repeats every week on Monday                      │   │
│ └───────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

**Fields:**
- **Checkbox** — "Repeat this task" (enables section)
- **Pattern dropdown** — Daily | Every other day | Weekly | Monthly | Yearly
- **Interval** — Number input + dynamic label (day(s)/week(s)/month(s))
- **Day selector** — 7 circular buttons (M T W T F S S), shown for Weekly pattern
- **End** — Radio: Never (default) | On date (date picker)
- **Live preview** — Updates as user changes settings, shows next occurrence + human-readable rule

**RRULE Generation (JavaScript):**

```javascript
function buildRRule(formData) {
    const freqMap = {
        'daily': 'DAILY',
        'every_other_day': 'DAILY',
        'weekly': 'WEEKLY',
        'monthly': 'MONTHLY',
        'yearly': 'YEARLY'
    };

    const rrule = {
        FREQ: freqMap[formData.pattern],
        DTSTART: formData.reminder_at || new Date().toISOString()
    };

    // Interval
    if (formData.pattern === 'every_other_day') {
        rrule.INTERVAL = 2;
    } else if (formData.interval > 1) {
        rrule.INTERVAL = formData.interval;
    }

    // Day selector (weekly only)
    if (formData.pattern === 'weekly' && formData.weekDays.length > 0) {
        rrule.BYDAY = formData.weekDays.map(d => dayAbbr[d]); // ['MO', 'TU', ...]
    }

    // End date
    if (formData.endType === 'on_date' && formData.endDate) {
        rrule.UNTIL = new Date(formData.endDate).toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
    }

    return JSON.stringify(rrule);
}
```

---

## 6. API Changes

### Tasks Endpoint — Additional Field

`GET /api/tasks` now includes:
- `disable_notifications: boolean`

### New Endpoint: Recurrence Rules CRUD

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/recurrence_rules` | Create rule for task |
| `PATCH` | `/api/recurrence_rules?id=eq.{id}` | Update rule |
| `DELETE` | `/api/recurrence_rules?id=eq.{id}` | Delete rule |

### PATCH /api/tasks — Bell Toggle

```javascript
// Toggle notifications for a task
PATCH /api/tasks?id=eq.{task-id}
Content-Type: application/json

{ "disable_notifications": true }

// Response: 200 OK
{ "disable_notifications": true, "updated_at": "2026-04-08T12:34:56Z" }
```

---

## 7. JavaScript App Structure Updates

### New Module: `App.Recurrence`

```javascript
App.Recurrence = {
    // Build RRULE from form data
    buildRRule: function(formData) { ... },

    // Parse RRULE to human-readable string (call backend or use library)
    humanReadable: function(rruleString) { ... },

    // Calculate next occurrence (call backend)
    nextOccurrence: function(rruleString, afterDate) { ... },

    // Toggle bell icon on card
    toggleNotifications: async function(taskId, currentState) {
        const newState = !currentState;
        try {
            const response = await fetch(`/api/tasks?id=eq.${taskId}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ disable_notifications: newState })
            });
            if (response.ok) {
                App.Alerts.Toast.fire({
                    icon: 'success',
                    title: newState ? 'Notifications muted' : 'Notifications enabled'
                });
                // Update card UI (bell icon)
                this.updateBellIcon(taskId, newState);
            }
        } catch (err) {
            App.Alerts.Toast.fire({ icon: 'error', title: 'Failed to update' });
        }
    },

    updateBellIcon: function(taskId, muted) {
        const bell = document.querySelector(`[data-task-id="${taskId}"] .bell-icon`);
        if (bell) {
            bell.textContent = muted ? '🔕' : '🔔';
            bell.parentElement.style.opacity = muted ? '0.85' : '1';
        }
    }
};
```

### Modal Updates

- Add recurrence section HTML
- Bind "Repeat this task" checkbox to show/hide recurrence fields
- Live preview updates on any field change (debounced 300ms)
- On save: if recurrence enabled, create/update `recurrence_rules` record

---

## 8. Error Handling

- **Invalid RRULE** — SweetAlert2 error: "Invalid recurrence pattern. Please check your settings."
- **No next occurrence** — Rule reaches `end_date` or max occurrences → mark `active = false`
- **Notification toggle fails** — Revert bell icon state, show error toast

---

## 9. Testing Considerations

- **Unit tests** — RRULE generation from form data
- **Integration tests** — SendReminders creates next occurrence correctly
- **Edge cases:**
  - Monthly on 31st → month with 30 days (php-rrule handles this)
  - Yearly on Feb 29 → non-leap year
  - Task deleted → cascade deletes `recurrence_rules`

---

## 10. Dependencies

| Package | Purpose |
|---------|---------|
| `rlanvin/php-rrule` | RFC 5545 RRULE parsing and calculation |

---

## 11. Future Enhancements (Out of Scope)

- Custom "Nth weekday of month" (e.g., "Last Friday")
- Exclusion dates (specific dates to skip)
- Edit all future occurrences vs. this occurrence only
- Recurrence preview calendar view
