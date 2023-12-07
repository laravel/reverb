<?php

namespace Laravel\Reverb;

class Application
{
    /**
     * Create a new application instance.
     */
    public function __construct(
        protected string $id,
        protected string $key,
        protected string $secret,
        protected int $pingInterval,
        protected array $allowedOrigins,
        protected int $maxMessageSize,
    ) {
        //
    }

    /**
     * Get the application ID.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get the application key.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Get the application secret.
     */
    public function secret(): string
    {
        return $this->secret;
    }

    /**
     * Get the allowed origins.
     *
     * @return array<int, string>
     */
    public function allowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**
     * Get the interval in minutes to ping the client.
     */
    public function pingInterval(): int
    {
        return $this->pingInterval;
    }

    /**
     * Get the maximum message size allowed from the client.
     */
    public function maxMessageSize(): int
    {
        return $this->maxMessageSize;
    }
}
