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
        protected int $activityTimeout,
        protected array $allowedOrigins,
        protected int $maxMessageSize,
        protected array $options = [],
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
     * Get the client ping interval in seconds.
     */
    public function pingInterval(): int
    {
        return $this->pingInterval;
    }

    /**
     * Get the activity timeout in seconds.
     */
    public function activityTimeout(): int
    {
        return $this->activityTimeout;
    }

    /**
     * Get the maximum message size allowed from the client.
     */
    public function maxMessageSize(): int
    {
        return $this->maxMessageSize;
    }

    /**
     * Get the application options.
     */
    public function options(): ?array
    {
        return $this->options;
    }

    /**
     * Convert the application to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'app_id' => $this->id,
            'key' => $this->key,
            'secret' => $this->secret,
            'ping_interval' => $this->pingInterval,
            'activity_timeout' => $this->activityTimeout,
            'allowed_origins' => $this->allowedOrigins,
            'max_message_size' => $this->maxMessageSize,
            'options' => $this->options,
        ];
    }
}
