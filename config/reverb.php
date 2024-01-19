<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Reverb Server
    |--------------------------------------------------------------------------
    |
    | This option controls the default server used by Reverb to handle
    | messages received from and when sending messages to connected
    | clients. You must specify one of the servers listed below.
    |
    | Supported: "reverb"
    */

    'default' => env('REVERB_SERVER', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Reverb Servers
    |--------------------------------------------------------------------------
    |
    | Here you may define details for each of the supported Reverb servers.
    | Each server has its own configuration options which are defined in
    | the array below. You should ensure all the options are present.
    */

    'servers' => [

        'reverb' => [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 8080),
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
            ],
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 15),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb Applications
    |--------------------------------------------------------------------------
    | Here you may define how Reverb applications are managed. Should you
    | wish to use the "config" provider, you should define an array of
    | apps which Reverb can handle including an id, key and secret.
    |
    */

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'id' => env('PUSHER_APP_ID'),
                'key' => env('PUSHER_APP_KEY'),
                'secret' => env('PUSHER_APP_SECRET'),
                'allowed_origins' => ['*'],
                'ping_interval' => env('REVERB_APP_PING_INTERVAL', 60),
                'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10000),
            ],
        ],

    ],

];
