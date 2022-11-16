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

        ],

        'api_gateway' => [

            'region' => env('REVERB_API_GATEWAY_REGION', 'us-east-1'),

            'endpoint' => env('REVERB_API_GATEWAY_ENDPOINT'),

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Cache Store
    |--------------------------------------------------------------------------
    |
    |
    */

    'connection_cache' => [

        'store' => env('REVERB_CONNECTION_CACHE', 'array'),

        'prefix' => env('REVERB_CONNECTION_CACHE_PREFIX', 'reverb'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Cache Store
    |--------------------------------------------------------------------------
    |
    |
    */

    'channel_cache' => [

        'store' => env('REVERB_CHANNEL_CACHE', 'array'),

        'prefix' => env('REVERB_CHANNEL_CACHE_PREFIX', 'reverb'),

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
