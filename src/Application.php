<?php

namespace Laravel\Reverb;

class Application
{
    public function __construct(
        protected string $id,
        protected string $key,
        protected string $secret,
        protected int $pingInterval,
        protected array $allowedOrigins
    )
    {}
    
    /**
     * Get the application ID.
     *
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get the application key.
     *
     * @return string
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Get the application secret.
     *
     * @return string
     */
    public function secret(): string
    {
        return $this->secret;
    }

    /**
     * Get the allowed origins.
     *
     * @return array
     */
    public function allowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**
     * Get the interval in minutes to ping the client.
     *
     * @return int
     */
    public function pingInterval(): int
    {
        return $this->pingInterval;
    }
}