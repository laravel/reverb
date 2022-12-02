<?php

use Laravel\Reverb\Tests\Connection;
use Laravel\Reverb\Tests\TestCase;
use Ramsey\Uuid\Uuid;

uses(TestCase::class)->in(__DIR__);

function connections(int $count = 1)
{
    return collect(range(1, $count))->map(fn () => new Connection(Uuid::uuid4()));
}

function validAuth(Connection $connection, string $channel, ?string $data = null)
{
    $signature = "{$connection->id()}:{$channel}";

    if ($data) {
        $signature .= ":{$data}";
    }

    return 'app-key:'.hash_hmac('sha256', $signature, 'pusher-secret');
}
