<?php

namespace Laravel\Reverb\Tests;

use Illuminate\Testing\Assert;
use Laravel\Reverb\Contracts\Connection as ConnectionInterface;

class Connection implements ConnectionInterface
{
    public $messages = [];

    public function identifier(): string
    {
        return '19c1c8e8-351b-4eb5-b6d9-6cbfc54a3446';
    }

    public function id(): string
    {
        return '10000.00001';
    }

    public function send(string $message): void
    {
        dump($message);
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
