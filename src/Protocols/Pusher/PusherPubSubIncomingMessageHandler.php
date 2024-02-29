<?php

namespace Laravel\Reverb\Protocols\Pusher;

use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;

class PusherPubSubIncomingMessageHandler implements PubSubIncomingMessageHandler
{
    /**
     * Handle an incoming message from the PubSub provider.
     */
    public function handle(string $payload): void
    {
        $event = json_decode($payload, true);

        match ($event['type'] ?? null) {
            'message' => EventDispatcher::dispatchSynchronously(
                unserialize($event['application']),
                $event['payload']
            ),
            'metrics' => app(MetricsHandler::class)->publish(
                unserialize($event['application']),
                $event['payload']['type'],
                $event['payload']['options'] ?? []
            ),
            default => null,
        };
    }
}
