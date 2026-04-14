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
        $sent = 0;

        Task::where('reminder_at', '<=', now())
            ->where('reminder_sent', false)
            ->each(function (Task $task) use (&$sent): void {
                try {
                    if (!$task->disable_notifications) {
                        $this->sendReminder($task);
                        $sent++;
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

        if ($sent > 0) {
            $this->info("Sent {$sent} reminder(s)");
        }

        return Command::SUCCESS;
    }

    private function sendReminder(Task $task): void
    {
        $routes = [];

        if (config('notifications.channels.pushover')) {
            $routes['pushover'] = config('services.pushover.user_key');
        }

        if (config('notifications.channels.twilio')) {
            $routes['twilio'] = config('services.twilio.to');
        }

        if (config('notifications.channels.rocketchat')) {
            $routes['rocketchat'] = null;
        }

        $routeNotification = Notification::routes($routes);
        $routeNotification->notify(new TaskReminder($task));
    }

    private function handleRecurrence(Task $task): void
    {
        $rule = RecurrenceRule::where('task_id', $task->id)
            ->where('active', true)
            ->first();

        if (!$rule) {
            return;
        }

        if ($rule->end_date && $rule->end_date < now()) {
            $rule->update(['active' => false]);
            return;
        }

        $rrule = new RRule(json_decode($rule->rrule, true));
        $nextOccurrence = $rrule->getNthOccurrenceAfter($task->reminder_at, 1);

        if (!$nextOccurrence) {
            $rule->update(['active' => false]);
            return;
        }

        $newTask = $task->replicate();
        $newTask->id = (string) Str::uuid();
        $newTask->reminder_at = $nextOccurrence;
        $newTask->reminder_sent = false;
        $newTask->save();

        $task->children()->each(function ($model) use ($newTask): void {
            /** @var Task $subtask */
            $subtask = $model;
            $newSubtask = $subtask->replicate();
            $newSubtask->id = (string) Str::uuid();
            $newSubtask->parent_id = $newTask->id;
            $newSubtask->save();
        });

        $nextAfter = $rrule->getNthOccurrenceAfter($nextOccurrence, 1);
        $rule->update([
            'next_occurrence_at' => $nextAfter
                ? $nextAfter->format('Y-m-d H:i:s')
                : $rule->next_occurrence_at,
        ]);
    }
}