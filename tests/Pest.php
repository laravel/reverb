<?php

use Laravel\Reverb\Tests\Connection;
use Laravel\Reverb\Tests\TestCase;
use Ramsey\Uuid\Uuid;

uses(TestCase::class)->in(__DIR__);

function connections(int $count = 1, array $data = [])
{
    return collect(range(1, $count))->map(fn ($item, $index) => [
        'connection' => new Connection(Uuid::uuid4()),
        'data' => empty($data) ? [] : $data + ['user_id' => $index + 1],
    ]);
}

function validAuth(Connection $connection, string $channel, ?string $data = null)
{
    $signature = "{$connection->id()}:{$channel}";

    if ($data) {
        $signature .= ":{$data}";
    }

    return 'app-key:'.hash_hmac('sha256', $signature, 'pusher-secret');
}
