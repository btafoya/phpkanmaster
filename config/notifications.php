<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Channel Toggles
    |--------------------------------------------------------------------------
    |
    | Each channel can be enabled/disabled independently via environment
    | variables. When disabled, the channel is not included in the
    | notification's via() method and no API calls are made.
    |
    */

    'channels' => [
        'pushover'   => env('NOTIFY_PUSHOVER', false),
        'twilio'     => env('NOTIFY_TWILIO', false),
        'rocketchat' => env('NOTIFY_ROCKETCHAT', false),
    ],

];