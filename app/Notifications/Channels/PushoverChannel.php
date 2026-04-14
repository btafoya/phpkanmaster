<?php

namespace App\Notifications\Channels;

use App\Notifications\Messages\PushoverMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushoverChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toPushover')) {
            return;
        }

        $message = $notification->toPushover($notifiable);

        if (!($message instanceof PushoverMessage)) {
            return;
        }

        $token   = config('services.pushover.token');
        $userKey = config('services.pushover.user_key');

        if (!$token || !$userKey) {
            Log::warning('PushoverChannel: missing token or user_key in config');

            return;
        }

        $payload = array_merge($message->toArray(), [
            'token'   => $token,
            'user'    => $userKey,
        ]);

        try {
            $response = Http::post('https://api.pushover.net/1/messages.json', $payload);

            if (!$response->successful()) {
                Log::error('PushoverChannel: API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('PushoverChannel: request failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}