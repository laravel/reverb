<?php

namespace Reverb\Ratchet;

use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\MessageComponentInterface;
use Reverb\Server as ReverbServer;

class Server implements MessageComponentInterface
{
    public function __construct(protected ReverbServer $server)
    {
    }

    public function onOpen(ConnectionInterface $connection)
    {
        $this->server->open($connection);
    }

    public function onMessage(ConnectionInterface $from, $message)
    {
        $this->server->message($from, $message);
    }

    public function onClose(ConnectionInterface $connection)
    {
        $this->server->close($connection);
    }

    public function onError(ConnectionInterface $connection, \Exception $e)
    {
        $this->server->error($connection, $e);
    }
}
