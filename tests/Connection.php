<?php

namespace Laravel\Reverb\Tests;

use Illuminate\Testing\Assert;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\GeneratesPusherIdentifiers;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\Connection as BaseConnection;

class Connection extends BaseConnection
{
    use GeneratesPusherIdentifiers;

    public $messages = [];

    public $identifier = '19c1c8e8-351b-4eb5-b6d9-6cbfc54a3446';

    public $id;

    public function __construct(?string $identifier = null)
    {
        if ($identifier) {
            $this->identifier = $identifier;
        }
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function id(): string
    {
        if (! $this->id) {
            $this->id = $this->generateId();
        }

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

    public function setLastSeenAt(int $time): Connection
    {
        $this->lastSeenAt = $time;

        return $this;
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

    public function assertSendCount(int $count): void
    {
        Assert::assertCount($count, $this->messages);
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
