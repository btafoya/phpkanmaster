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
            'reminder_at' => $this->task->reminder_at?->format('c'),
        ];
    }
}
