<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Reverb Server Name
    |--------------------------------------------------------------------------
    |
    |
    */

    'default' => env('REVERB_SERVER', 'ratchet'),

    /*
    |--------------------------------------------------------------------------
    | Reverb Servers
    |--------------------------------------------------------------------------
    |
    |
    */

    'servers' => [

        'ratchet' => [
            'host' => env('REVERB_RATCHET_HOST', '127.0.0.1'),
            'port' => env('REVERB_RATCHET_PORT', 8080),
            'connection_manager' => [
                'prefix' => env('REVERB_RATCHET_CONNECTION_CACHE_PREFIX', 'reverb'),
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
    |
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

    /*
    |--------------------------------------------------------------------------
    | Publish / Subscribe Settings
    |--------------------------------------------------------------------------
    |
    |
    */

    'pubsub' => [
        'enabled' => env('REVERB_PUBSUB_ENABLED', false),
        'channel' => env('REVERB_PUBSUB_CHANNEL', 'reverb'),
    ],

];
