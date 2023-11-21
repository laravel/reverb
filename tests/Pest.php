<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Managers\Connections;
use Laravel\Reverb\Servers\Reverb\ChannelConnection;
use Laravel\Reverb\Tests\Connection;
use Laravel\Reverb\Tests\SerializableConnection;
use Laravel\Reverb\Tests\TestCase;
use Ramsey\Uuid\Uuid;

uses(TestCase::class)->in(__DIR__.'/Unit');

/**
 * Create a defined number of connections.
 *
 * @param  bool  $serializable
 * @return array<int, \Laravel\Reverb\Connection|string>
 */
function connections(int $count = 1, array $data = [], $serializable = false): array
{
    return Collection::make(range(1, $count))->map(function () use ($data, $serializable) {
        return new ChannelConnection(
            $serializable
                ? new SerializableConnection(Uuid::uuid4())
                : new Connection(Uuid::uuid4()),
            $data
        );
    })->all();
}

/**
 * Generate a valid Pusher authentication signature.
 */
function validAuth(string $connectionId, string $channel, string $data = null): string
{
    $signature = "{$connectionId}:{$channel}";

    if ($data) {
        $signature .= ":{$data}";
    }

    return 'app-key:'.hash_hmac('sha256', $signature, 'pusher-secret');
}

/**
 * Return the connection manager.
 */
function channelManager(Application $app = null): ChannelManager
{
    return App::make(ChannelManager::class)
        ->for($app ?: App::make(ApplicationProvider::class)->all()->first());
}
