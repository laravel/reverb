<?php

namespace Laravel\Reverb\Tests;

use Carbon\Carbon;
use Illuminate\Testing\Assert;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\Connection as BaseConnection;
use Laravel\Reverb\Contracts\ApplicationProvider;

class Connection extends BaseConnection
{
    public $messages = [];

    public $identifier = '19c1c8e8-351b-4eb5-b6d9-6cbfc54a3446';

    public $id = '10000.00001';

    public function __construct(string $identifier = null)
    {
        if ($identifier) {
            $this->identifier = $identifier;
        }
        $this->lastSeenAt = now();
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function app(): Application
    {
        return app()->make(ApplicationProvider::class)->findByKey('pusher-key');
    }

    public function origin(): string
    {
        return 'http://localhost';
    }

    public function setLastSeenAt(Carbon $lastSeenAt): void
    {
        $this->lastSeenAt = $lastSeenAt;
    }

    public function setHasBeenPinged(): void
    {
        $this->hasBeenPinged = true;
    }

    public function send(string $message): void
    {
        $this->messages[] = $message;
    }

    public function terminate(): void
    {
        //
    }

    public function assertSent(array $message): void
    {
        Assert::assertContains(json_encode($message), $this->messages);
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->messages);
    }

    public function assertHasBeenPinged(): void
    {
        Assert::assertTrue($this->hasBeenPinged);
    }
}
