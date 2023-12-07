# Laravel Reverb
Laravel Reverb brings real-time WebSocket communication for Laravel applications.

## Architecture

### Message Lifecycle
When the `reverb:start` [command](https://github.com/laravel/reverb/blob/main/src/Servers/Reverb/Console/Commands/StartServer.php) is run, a new React PHP socket server and event loop are created and started on a given host and port.

Reverb wraps the socket server and a lightweight request router in its own [HTTP server](https://github.com/laravel/reverb/blob/main/src/Http/Server.php) class which decides how to route the request.

To do this, it first attempts to turn the incoming socket connection into a PSR7 RequestInterface object before passing it to the [router](https://github.com/laravel/reverb/blob/main/src/Http/Router.php) to determine which controller should process it by matching the request path.

The router will also determine whether the given request is attempting a WebSocket connection by inspecting the incoming headers. If it is, it will attempt to negotiate the upgrade with the client and, if successful, [decorate the connection](https://github.com/laravel/reverb/blob/main/src/WebSockets/WsConnection.php) object with the WebSocket connection details and pass it along to the controller.

If it’s not a WebSocket request, the PSR7 object will be send directly to the matching controller which processes the request and writes the response back to the connection and closes it.

In the [WebSocket controller](https://github.com/laravel/reverb/blob/main/src/Servers/Reverb/Controller.php), the connection is decorated again with information such as the Pusher socket ID and the app the user is connected to. We also validate the app is valid at this point.

The WebSocket connection object instantiates a [MessageBuffer](https://github.com/ratchetphp/RFC6455/blob/master/src/Messaging/MessageBuffer.php) provided by the Ratchet RFC6455 (WebSocket spec) package. This handles parsing and decoding the messages received across the socket connection. This buffer is opened in the controller and event listeners are hooked up. 

Events emitted from the connection are routed through the message buffer. When a message has been parsed, it is passed to the [Pusher Server](https://github.com/laravel/reverb/blob/main/src/Pusher/Server.php) which takes the message and decides how to route it.

First, it checks the [Pusher protocol](https://github.com/laravel/reverb/blob/main/src/Pusher/Event.php) and responds accordingly to open connections, channel subscriptions, disconnections, etc. If the message is not part of the Pusher protocol, a check is made for [client events](https://github.com/laravel/reverb/blob/main/src/ClientEvent.php) such as whispers. Essentially here, it’s a string match on the start of the message to see whether it’s something which requires a response. In the event it does, it’s written back to the socket.

### Channel Subscription
Reverb provides a [driver based approach](https://github.com/laravel/reverb/tree/main/src/Managers) to channel subscription. For standard Reverb, an in-memory array channel store is used, but for something like API Gateway which can’t store connections in memory, a cache driver alternative can be used.

When a user subscribes to a channel, Reverb attempts to resolve the channel from the manager and connects the user to it. When the user disconnects, they are removed from the channel. When there are no connections in the channel, it’s removed from memory.

One core part of channels is the ability to broadcast to all connections of the channel. This is achieved by two routes which are registered in the router. One triggers a single event, the other triggers multiple events. this is the endpoint Laravel’s broadcasting functionality hooks into.

When either of these endpoints are hit, the relevant channel is pulled from the channel manager, all connections are iterated over and the message written to the socket.

Reverb supports seven channel types:

- **Standard Channels** - public channel accessible to anyone
- **Private channels** - user must be authenticated so there is a signature verification check
- **Presence Channels** - user must be authenticated so there is a signature verification check. User subscribes with additional information which can be shared with other members of the channel
- **Cache Channels** - public channel where the last message broadcast is stored and sent to any new connections
- **Private Cache Channels** - a mix of the private and cache channels above
- **Presence Cache Channels** - a mix of the presence and cache channels above
- **Encrypted channels** - Reverb is dumb here, it just forwards encrypted messages sent from server or client

### API Gateway
The API Gateway implementation leverages the WebSocket API type. The user connects and the request is sent to Lambda for processing. When a user connects, disconnects or sends a message it is sent via API Gateway to Lambda. Reverb takes the request creates a Reverb Connection object from it and sends the payload on to the Reverb server for processing. The channel manager cannot be in memory, so a cache manager is used instead. Messages cannot be sent in response to the request so, instead are queued and posted to the endpoint provided by AWS. In turn, they send the message on to the user.

## Outstanding
- [ ] **Pulse Card(s)** - this can interact with some of the addtional Pusher endpoints in order to show channels and connections

## Considerations
- The WebSocket specification has a concept of extensions. They are completely optional and Reverb doesn't support them right now. There is only really one official extension which allows inflating and deflating messages. Moving forward, it could be possible to add hooks / middleware to the message lifecycle to allow forextensions such as permessage-deflate, but also to allow others to implement their own.
- Pusher now allow opt-in access to something called [Watchlists](https://pusher.com/docs/channels/using_channels/watchlist-events/). It could be possible to look at implementing this functionality.

## Installation
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
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: import.meta.env.VITE_PUSHER_HOST,
    wsPort: import.meta.env.VITE_PUSHER_PORT,
    wssPort: import.meta.env.VITE_PUSHER_PORT,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
})

// Using Mix
new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    wsHost: process.env.MIX_PUSHER_HOST,
    wsPort: process.env.MIX_PUSHER_PORT,
    wssPort: process.env.MIX_PUSHER_PORT,
    forceTLS: (process.env.MIX_PUSHER_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
})
```