<?php

namespace App\Notifications;

use App\Models\Task;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\Channels\RocketChatChannel;
use App\Notifications\Channels\TwilioChannel;
use App\Notifications\Messages\PushoverMessage;
use App\Notifications\Messages\RocketChatMessage;
use App\Notifications\Messages\TwilioMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskReminder extends Notification
{
    use Queueable;

    public function __construct(public readonly Task $task) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (config('notifications.channels.pushover')) {
            $channels[] = PushoverChannel::class;
        }

        if (config('notifications.channels.twilio')) {
            $channels[] = TwilioChannel::class;
        }

        if (config('notifications.channels.rocketchat')) {
            $channels[] = RocketChatChannel::class;
        }

        // Always include at least one channel so the notification is dispatched
        if (empty($channels)) {
            $channels[] = 'log';
        }

        return $channels;
    }

    public function toPushover(object $notifiable): PushoverMessage
    {
        $message = PushoverMessage::create($this->task->title)
            ->title('Task Reminder')
            ->priority($this->task->pushover_priority);

        if ($this->task->pushover_priority === 2) {
            $message->retry($this->task->pushover_retry ?? 30)
                ->expire($this->task->pushover_expire ?? 3600);
        }

        return $message;
    }

    public function toTwilio(object $notifiable): TwilioMessage
    {
        return TwilioMessage::create("Reminder: {$this->task->title}");
    }

    public function toRocketChat(object $notifiable): RocketChatMessage
    {
        $dueDate = $this->task->due_date ? " — due {$this->task->due_date}" : '';

        return RocketChatMessage::create(
            "Reminder: *{$this->task->title}*{$dueDate}"
        )->channel(config('services.rocketchat.channel', '#general'));
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