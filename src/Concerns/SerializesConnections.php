<?php

namespace Laravel\Reverb\Concerns;

use Laravel\Reverb\Application;

trait SerializesConnections
{
    /**
     * Prepare the connection instance values for serialization.
     *
     * @return array
     */
    public function __serialize()
    {
        return [
            'id' => $this->id(),
            'identifier' => $this->identifier(),
            'application' => $this->app()->id(),
            'lastSeenAt' => $this->lastSeenAt,
            'hasBeenPinged' => $this->hasBeenPinged,
        ];
    }

    /**
     * Restore the connection after serialization.
     *
     * @param  array  $values
     * @return void
     */
    public function __unserialize(array $values)
    {
        $this->id = $values['id'];
        $this->identifier = $values['identifier'];
        $this->application = Application::findById($values['application']);
        $this->lastSeenAt = $values['lastSeenAt'] ?? null;
        $this->hasBeenPinged = $values['hasBeenPinged'] ?? null;
    }
}
