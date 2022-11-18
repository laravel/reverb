<?php

namespace Laravel\Reverb\Tests;

use Illuminate\Testing\Assert;
use Laravel\Reverb\Contracts\Connection as ConnectionInterface;

class Connection implements ConnectionInterface
{
    public $messages = [];

    public $identifier = '19c1c8e8-351b-4eb5-b6d9-6cbfc54a3446';

    public $id = '10000.00001';

    public function __construct(string $identifier = null)
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
        return $this->id;
    }

    public function send(string $message): void
    {
        $this->messages[] = $message;
    }

    public function assertSent(array $message): void
    {
        Assert::assertContains(json_encode($message), $this->messages);
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->messages);
    }
}
