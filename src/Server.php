<?php

namespace Reverb;

use Exception;
use Reverb\Contracts\ChannelManager;

class Server
{
    protected $manager;

    public function __construct()
    {
        $this->manager = app(ChannelManager::class);
    }

    public function open($connection)
    {
        $this->manager->add($connection);

        echo "New connection: ({$connection->resourceId})".PHP_EOL;

        $connection->send(json_encode([
            'event' => 'pusher:connection_established',
            'data' => json_encode([
                'socket_id' => $connection->resourceId,
                'activity_timeout' => 30,
            ]),
        ]));
    }

    public function message($from, $message)
    {
        echo 'Message from '.$from->resourceId.': '.$message.PHP_EOL;
    }

    public function close($connection)
    {
        echo "Connection {$connection->resourceId} has disconnected".PHP_EOL;
    }

    public function error($connection, Exception $e)
    {
        echo 'Error: '.$e->getMessage();
    }
}
