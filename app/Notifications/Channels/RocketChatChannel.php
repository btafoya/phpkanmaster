<?php

namespace App\Notifications\Channels;

use App\Notifications\Messages\RocketChatMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RocketChatChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toRocketChat')) {
            return;
        }

        $message = $notification->toRocketChat($notifiable);

        if (!($message instanceof RocketChatMessage)) {
            return;
        }

        $url   = config('services.rocketchat.url');
        $token = config('services.rocketchat.token');

        if (!$url || !$token) {
            Log::warning('RocketChatChannel: missing url or token in config');

            return;
        }

        $payload = $message->toArray();

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $token,
            ])->post($url, $payload);

            if (!$response->successful()) {
                Log::error('RocketChatChannel: API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('RocketChatChannel: request failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}