<?php

return [

    'default' => env('REVERB_DRIVER', 'ratchet'),

    'drivers' => [

        'ratchet' => [

        ],

        'api_gateway' => [],

    ],

    'connection_cache' => 'array',

    'channel_cache' => 'file',

];
