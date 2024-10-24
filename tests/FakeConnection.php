<?php

namespace Laravel\Reverb\Tests;

use Illuminate\Testing\Assert;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\GeneratesIdentifiers;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\Connection as BaseConnection;
use Ratchet\RFC6455\Messaging\Frame;

class FakeConnection extends BaseConnection
{
    use GeneratesIdentifiers;

    /**
     * Messages received by the connection.
     *
     * @var array<int, string>
     */
    public $messages = [];

    /**
     * Connection identifier.
     */
    public string $identifier = '19c1c8e8-351b-4eb5-b6d9-6cbfc54a3446';

    /**
     * Connection socket ID.
     *
     * @var string
     */
    public $id;

    /**
     * Create a new fake connection instance.
     */
    public function __construct(?string $identifier = null, ?string $origin = null)
    {
        if ($identifier) {
            $this->identifier = $identifier;
        }

        $this->origin = $origin ?? 'http://localhost';
    }

    /**
     * Get the raw socket connection identifier.
     */
    public function identifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get the normalized socket ID.
     */
    public function id(): string
    {
        if (! $this->id) {
            $this->id = $this->generateId();
        }

        return $this->id;
    }

    /**
     * Get the application the connection belongs to.
     */
    public function app(): Application
    {
        return app()->make(ApplicationProvider::class)->findByKey('reverb-key');
    }

    /**
     * Set the connection last seen at timestamp.
     */
    public function setLastSeenAt(int $time): FakeConnection
    {
        $this->lastSeenAt = $time;

        return $this;
    }

    /**
     * Set the connection as pinged.
     */
    public function setHasBeenPinged(): void
    {
        $this->hasBeenPinged = true;
    }

    /**
     * Send a message to the connection.
     */
    public function send(string $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Send a control frame to the connection.
     */
    public function control(string $type = Frame::OP_PING): void {}

    /**
     * Terminate a connection.
     */
    public function terminate(): void
    {
        //
    }

    /**
     * Assert the given message was received by the connection.
     */
    public function assertReceived(array $message): void
    {
        Assert::assertContains(json_encode($message), $this->messages);
    }

    /**
     * Assert the connection received the given message count.
     */
    public function assertReceivedCount(int $count): void
    {
        Assert::assertCount($count, $this->messages);
    }

    /**
     * Assert the connection didn't receive any messages.
     */
    public function assertNothingReceived(): void
    {
        Assert::assertEmpty($this->messages);
    }

    /**
     * Assert the connection has been pinged.
     */
    public function assertHasBeenPinged(): void
    {
        Assert::assertTrue($this->hasBeenPinged);
    }
}
