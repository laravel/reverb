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

        EventDispatcher::dispatchSynchronously(
            unserialize($event['application']),
            $event['payload']
        );
    }
}
