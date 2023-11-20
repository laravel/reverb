<?php

namespace Laravel\Reverb\Servers\Reverb;

use Laravel\Reverb\Contracts\Connection;

class ChannelConnection
{
    public function __construct(protected Connection $connection, protected array $data = [])
    {
        //
    }

    /**
     * Get the underlying connection.
     */
    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * Get the connection data.
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Call the method on the connection.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection->{$method}(...$parameters);
    }
}
