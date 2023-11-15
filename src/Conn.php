<?php

namespace Laravel\Reverb;

use BadMethodCallException;
use React\Socket\ConnectionInterface;

class Conn
{
    protected $id;

    protected $initilized = false;

    protected $buffer = '';

    public function __construct(protected ConnectionInterface $connection)
    {
        $this->id = (int) $connection->stream;
    }

    public function id()
    {
        return $this->id;
    }

    public function initialize()
    {
        $this->initilized = true;
    }

    public function isInitialized()
    {
        return $this->initilized;
    }

    public function hasBuffer()
    {
        return $this->buffer !== '';
    }

    public function send($data)
    {
        $this->connection->write($data);

        return $this;
    }

    public function close()
    {
        $this->connection->end();

        return $this;
    }

    public function __call($method, $parameters)
    {
        if (! method_exists($this->connection, $method)) {
            throw new BadMethodCallException("Method [{$method}] does not exist on [".get_class($this->connection).'].');
        }

        return $this->connection->{$method}(...$parameters);
    }
}