<?php

namespace Reverb\ChannelManagers;

use Reverb\Contracts\ChannelManager;
use SplObjectStorage;

class ArrayManager implements ChannelManager
{
    protected $connections;

    public function __construct()
    {
        $this->connections = new SplObjectStorage;
    }

    public function add($connection)
    {
        $this->connections->attach($connection);
    }

    public function remove($connection)
    {
        $this->connections->detach($connection);
    }

    public function all()
    {
        return $this->connections;
    }
}
