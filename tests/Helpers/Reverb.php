<?php

use Illuminate\Support\Str;
use Laravel\Reverb\Tests\TestConnection;
use Ratchet\Client\WebSocket;
use React\Promise\Deferred;
use React\Promise\Timer\TimeoutException;

use function Ratchet\Client\connect as connector;
use function React\Async\await;
use function React\Promise\Timer\timeout;

/**
 * Connect to the WebSocket server.
 */
function connect(string $host = '0.0.0.0', string $port = '8080', string $key = 'pusher-key', array $headers = []): TestConnection
{
    $promise = new Deferred;

    $connection = await(
        connector("ws://{$host}:{$port}/app/{$key}", headers: $headers)
    );

    $connection = new TestConnection($connection);

    $connection->on('message', function ($message) use ($promise) {
        $promise->resolve((string) $message);
    });

    $message = await($promise->promise());

    expect($message)->toContain('connection_established');

    $connection->socketId = json_decode(json_decode($message)->data)->socket_id;

    return $connection;
}

/**
 * Send a message to the connected client.
 */
function send(array $message, ?TestConnection $connection = null)
{
    $connection = $connection ?: connect();

    $connection->send(json_encode($message));

    return $connection->await();
}

/**
 * Subscribe to a channel.
 */
function subscribe(string $channel, ?array $data = [], ?string $auth = null, ?TestConnection $connection = null): string
{
    $data = ! empty($data) ? json_encode($data) : null;

    if (! $auth && Str::startsWith($channel, ['private-', 'presence-'])) {
        $connection = $connection ?: connect();
        $auth = validAuth($connection->socketId(), $channel, $data);
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
 * Unsubscribe to a channel.
 */
function unsubscribe(string $channel, ?TestConnection $connection = null): ?string
{
    return send([
        'event' => 'pusher:unsubscribe',
        'data' => ['channel' => $channel],
    ], $connection);
}

/**
 * Disconnect the connected client.
 */
function disconnect(TestConnection $connection): string
{
    $promise = new Deferred;

    $connection->on('close', function () use ($promise) {
        $promise->resolve('Connection Closed.');
    });

    $connection->close();

    return await($promise->promise());
}

/**
 * Return a promise when a given connection is disconnected.
 *
 * @param  \Ratchet\Client\WebSocketWebSocket  $connection
 */
function disconnectPromise(WebSocket $connection): PromiseInterface
{
    $promise = new Deferred;

    $connection->on('close', function ($message) use ($promise) {
        $promise->resolve('Connection Closed.');
    });

    return $promise->promise();
}
