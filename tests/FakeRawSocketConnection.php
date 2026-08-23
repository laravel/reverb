<?php

namespace Laravel\Reverb\Tests;

use React\Socket\ConnectionInterface;
use React\Stream\WritableStreamInterface;

/**
 * A minimal fake of a raw ReactPHP socket connection, used to build a real
 * `Laravel\Reverb\Servers\Reverb\Http\Connection` in tests without a socket.
 */
class FakeRawSocketConnection implements ConnectionInterface
{
    public $stream = 1;

    /**
     * Data written to the connection.
     *
     * @var array<int, string>
     */
    public array $written = [];

    public bool $ended = false;

    public function write($data)
    {
        $this->written[] = $data;

        return true;
    }

    public function end($data = null)
    {
        $this->ended = true;
    }

    public function close()
    {
        //
    }

    public function pause()
    {
        //
    }

    public function resume()
    {
        //
    }

    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        return $dest;
    }

    public function isReadable()
    {
        return true;
    }

    public function isWritable()
    {
        return true;
    }

    public function getRemoteAddress()
    {
        return null;
    }

    public function getLocalAddress()
    {
        return null;
    }

    public function on($event, callable $listener)
    {
        //
    }

    public function once($event, callable $listener)
    {
        //
    }

    public function removeListener($event, callable $listener)
    {
        //
    }

    public function removeAllListeners($event = null)
    {
        //
    }

    public function listeners($event = null)
    {
        return [];
    }

    public function emit($event, array $arguments = [])
    {
        //
    }
}
