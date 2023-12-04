<?php

use Illuminate\Support\Str;
use Ratchet\Client\WebSocket;
use React\Promise\Deferred;

use function Ratchet\Client\connect as connector;
use function React\Async\await;

/**
 * Connect to the WebSocket server.
 */
function connect(string $host = '0.0.0.0', string $port = '8080', string $key = 'pusher-key', array $headers = []): WebSocket
{
    $promise = new Deferred;

    $connection = await(
        connector("ws://{$host}:{$port}/app/{$key}", headers: $headers)
    );

    $connection->on('message', function ($message) use ($promise) {
        $promise->resolve((string) $message);
    });

    $message = await($promise->promise());

    expect($message)->toContain('connection_established');

    return $connection;
}

/**
 * Send a message to the connected client.
 */
function send(array $message, WebSocket $connection = null): string
{
    $promise = new Deferred;

    $connection = $connection ?: connect();

    $connection->on('message', function ($message) use ($promise) {
        $promise->resolve((string) $message);
    });

    $connection->on('close', function ($code, $message) use ($promise) {
        $promise->resolve((string) $message);
    });

    $connection->send(json_encode($message));

    return await($promise->promise());
}

/**
 * Subscribe to a channel.
 */
function subscribe(string $channel, ?array $data = [], string $auth = null, WebSocket $connection = null): string
{
    $data = ! empty($data) ? json_encode($data) : null;

    if (! $auth && Str::startsWith($channel, ['private-', 'presence-'])) {
        $connection = $connection ?: connect();
        $auth = validAuth(socketId($connection), $channel, $data);
    }

    return send([
        'event' => 'pusher:subscribe',
        'data' => array_filter([
            'channel' => $channel,
            'channel_data' => $data,
            'auth' => $auth,
        ]),
    ], $connection);
}

/**
 * Disconnect the connected client.
 */
function disconnect(WebSocket $connection): string
{
    $promise = new Deferred;

    $connection->on('close', function () use ($promise) {
        $promise->resolve('Connection Closed.');
    });

    $connection->close();

    return await($promise->promise());
}

function socketId(WebSocket $connection)
{
    $connections = channelManager()->connections();

    dd($connections);
}
