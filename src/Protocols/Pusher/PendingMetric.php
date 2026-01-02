<?php

namespace Laravel\Reverb\Protocols\Pusher;

use Laravel\Reverb\Application;

class PendingMetric
{
    protected int $subscribers = 0;

    protected array $metrics = [];

    public function __construct(
        protected string $key,
        protected Application $application,
        protected MetricType $type,
        protected array $options = [],
    ) {
        //
    }

    public function key(): string
    {
        return $this->key;
    }

    public function type(): MetricType
    {
        return $this->type;
    }
    
    public function application(): Application
    {
        return $this->application;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function setSubscriberCount(int $count): void
    {
        $this->subscribers = $count;
    }

    public function append(array $metrics): void
    {
        $this->metrics[] = $metrics;
    }

    public function resolvable(): bool
    {
        return count($this->metrics) === $this->subscribers;
    }

    public function resolve(): array
    {
        return $this->metrics;
    }
}
