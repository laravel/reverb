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
    | Supported: "ratchet", "api_gateway"
    */

    'default' => env('REVERB_SERVER', 'ratchet'),

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

        'ratchet' => [
            'host' => env('REVERB_RATCHET_HOST', '127.0.0.1'),
            'port' => env('REVERB_RATCHET_PORT', 8080),
            'connection_manager' => [
                'prefix' => env('REVERB_RATCHET_CONNECTION_CACHE_PREFIX', 'reverb'),
            ],
            'publish_events' => [
                'enabled' => env('REVERB_RATCHET_SCALING_ENABLED', false),
                'channel' => env('REVERB_RATCHET_SCALING_CHANNEL', 'reverb'),
            ],
        ],

        'api_gateway' => [
            'region' => env('REVERB_API_GATEWAY_REGION', 'us-east-1'),
            'endpoint' => env('REVERB_API_GATEWAY_ENDPOINT'),
            'connection_manager' => [
                'store' => env('REVERB_API_GATEWAY_CONNECTION_CACHE', 'dynamodb'),
                'prefix' => env('REVERB_API_GATEWAY_CONNECTION_CACHE_PREFIX', 'reverb'),
            ],
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
                'ping_interval' => env('REVERB_APP_PING_INTERVAL', 5),
            ],
        ],

    ],

];
