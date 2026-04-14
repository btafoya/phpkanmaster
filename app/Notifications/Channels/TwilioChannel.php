<?php

namespace App\Notifications\Channels;

use App\Notifications\Messages\TwilioMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toTwilio')) {
            return;
        }

        $message = $notification->toTwilio($notifiable);

        if (!($message instanceof TwilioMessage)) {
            return;
        }

        $sid       = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $from      = $message->from ?? config('services.twilio.from');
        $to        = $message->to ?? config('services.twilio.to');

        if (!$sid || !$authToken || !$from || !$to) {
            Log::warning('TwilioChannel: missing credentials in config');

            return;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        try {
            $response = Http::asForm()->withBasicAuth($sid, $authToken)->post($url, [
                'From' => $from,
                'To'   => $to,
                'Body' => $message->content,
            ]);

            if (!$response->successful()) {
                Log::error('TwilioChannel: API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('TwilioChannel: request failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}