<?php

namespace Reverb\Concerns;

trait GeneratesPusherIdentifiers
{
    /**
     * Generate a Pusher-compatible socket ID.
     *
     * @return string
     */
    protected function generateId(): string
    {
        return sprintf('%d.%d', random_int(1, 1000000000), random_int(1, 1000000000));
    }
}
