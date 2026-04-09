<?php

namespace Tests\Feature;

use App\Models\RecurrenceRule;
use App\Models\Task;
use App\Notifications\TaskReminder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SendRemindersTest extends TestCase
{
    use DatabaseMigrations;

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
        $task = $this->makeTask(['reminder_at' => now()->subDay()]);

        $rule = new RecurrenceRule([
            'id'                 => (string) Str::uuid(),
            'task_id'            => $task->id,
            'rrule'              => json_encode(['FREQ' => 'DAILY', 'DTSTART' => now()->subDay()->format('Ymd\THis\Z')]),
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
        $task = $this->makeTask(['reminder_at' => now()->subDay()]);

        // UNTIL is before now, so no next occurrence exists
        $rule = new RecurrenceRule([
            'id'                 => (string) Str::uuid(),
            'task_id'            => $task->id,
            'rrule'              => json_encode([
                'FREQ'    => 'DAILY',
                'DTSTART' => now()->subDays(3)->format('Ymd\THis\Z'),
                'UNTIL'   => now()->subDay()->format('Ymd\THis\Z'),
            ]),
            'next_occurrence_at' => now()->addDay(),
            'active'             => true,
        ]);
        $rule->save();

        $this->artisan('reminders:send')->assertSuccessful();

        $this->assertDatabaseCount('tasks', 1);
        $this->assertFalse(RecurrenceRule::find($rule->id)->active);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

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
