# Laravel Reverb

## Introduction

Laravel Reverb brings real-time WebSocket communication for Laravel applications.

### Installation

Add the following `repostories` block to your `composer.json` file.

```json
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/laravel/reverb"
    }
]
```

Now, install the package.

```shell
composer require laravel/reverb
```

### Run Socket Server

```shell
php artisan reverb:start
```

This will start the server running on localhost port 8080. You may use the `--host` and `--port` should you wish.

### Install Dependencies

Follow the instructions to [install the Pusher Channels SDK](https://laravel.com/docs/9.x/broadcasting#pusher-channels).

Follow the instructions to [install Echo](https://laravel.com/docs/9.x/broadcasting#client-side-installation).

### Update Environment

```
BROADCAST_DRIVER=pusher

PUSHER_APP_ID=123
PUSHER_APP_KEY=456
PUSHER_APP_SECRET=abc
PUSHER_HOST=localhost
PUSHER_PORT=8080
PUSHER_SCHEME=http

# Using Vite
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

# Using Mix
MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
MIX_PUSHER_HOST="${PUSHER_HOST}"
MIX_PUSHER_PORT="${PUSHER_PORT}"
MIX_PUSHER_SCHEME="${PUSHER_SCHEME}"
MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### Service Provider

Uncomment the `BroadcastServiceProvider` in the `app.php` config file.

```php
App\Providers\BroadcastServiceProvider::class,
```

### Update Echo Configuration

```javascript
// Using Vite
new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    wsHost: import.meta.env.VITE_PUSHER_HOST,
    wsPort: import.meta.env.VITE_PUSHER_PORT,
    wssPort: import.meta.env.VITE_PUSHER_PORT,
    forceTLS: import.meta.env.VITE_PUSHER_SCHEME,
    enabledTransports: ['ws', 'wss'],
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
})

// Using Mix
new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    wsHost: process.env.MIX_PUSHER_HOST,
    wsPort: process.env.MIX_PUSHER_PORT,
    wssPort: process.env.MIX_PUSHER_PORT,
    forceTLS: process.env.MIX_PUSHER_SCHEME,
    enabledTransports: ['ws', 'wss'],
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
})
```