<?php

use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Connection as ReverbConnection;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Managers\Connections;
use Laravel\Reverb\Tests\Connection;
use Laravel\Reverb\Tests\SerializableConnection;
use Laravel\Reverb\Tests\TestCase;
use Ramsey\Uuid\Uuid;

uses(TestCase::class)->in(__DIR__.'/Unit');

/**
 * Create a defined number of connections.
 *
 * @param  bool  $serializable
 * @return \Laravel\Reverb\Managers\Connections|\Laravel\Reverb\Connection[]|string[]
 */
function connections(int $count = 1, $serializable = false): Connections
{
    return Connections::make(range(1, $count))->map(function () use ($serializable) {
        return $serializable
                    ? new SerializableConnection(Uuid::uuid4())
                    : new Connection(Uuid::uuid4());
    });
}

/**
 * Generate a valid Pusher authentication signature.
 */
function validAuth(ReverbConnection $connection, string $channel, string $data = null): string
{
    $signature = "{$connection->id()}:{$channel}";

    if ($data) {
        $signature .= ":{$data}";
    }

    return 'app-key:'.hash_hmac('sha256', $signature, 'pusher-secret');
}

/**
 * Return the connection manager.
 */
function connectionManager(Application $app = null): ConnectionManager
{
    return App::make(ConnectionManager::class)
        ->for($app ?: App::make(ApplicationProvider::class)->all()->first());
}

/**
 * Return the connection manager.
 */
function channelManager(Application $app = null): ChannelManager
{
    return App::make(ChannelManager::class)
        ->for($app ?: App::make(ApplicationProvider::class)->all()->first());
}
