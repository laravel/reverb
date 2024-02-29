<?php

namespace Laravel\Reverb\Protocols\Pusher;

use Laravel\Reverb\Application;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;

class MetricsHandler
{
    /**
     * Handle the incoming metrics request.
     */
    public static function handle(Application $application, array $payload): void
    {
        app(PubSubProvider::class)->publish(
            ['type' => 'metrics-retrieved', 'payload' => ['data' => ['goes' => 'here']]]
        );
    }

    public function get(string $type): array
    {
        return match ($type) {
            'connections' => $this->getConnections(),
        };
    }

    public function getConnections(): array
    {
        return $this->channels->connections();
    }
}
