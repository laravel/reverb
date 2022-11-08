<?php

namespace Reverb\Concerns;

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
    }
}
