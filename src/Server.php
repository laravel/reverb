<?php

namespace Reverb;

use Exception;
use Illuminate\Support\Str;
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

        $this->generateSocketId($connection);

        echo "New connection: ({$connection->socketId})".PHP_EOL;

        $connection->send(json_encode([
            'event' => 'pusher:connection_established',
            'data' => json_encode([
                'socket_id' => $connection->socketId,
                'activity_timeout' => 30,
            ]),
        ]));
    }

    public function message($from, $message)
    {
        $event = json_decode($message, true);

        if (Str::contains($event['event'], 'pusher:')) {
            $from->send($this->handlePusherMessage($event, $from));
        }

        echo 'Message from '.$from->socketId.': '.$message.PHP_EOL;
    }

    public function close($connection)
    {
        echo "Connection {$connection->socketId} has disconnected".PHP_EOL;
    }

    public function error($connection, Exception $e)
    {
        echo 'Error: '.$e->getMessage().PHP_EOL;
    }

    protected function handlePusherMessage(array $event, $connection)
    {
        if ($event['event'] === 'pusher:ping') {
            return json_encode([
                'event' => 'pusher:pong',
                'data' => json_encode([
                    'socket_id' => $connection->socketId,
                ]),
            ]);
        }
    }

    protected function generateSocketId($connection)
    {
        $socketId = sprintf('%d.%d', random_int(1, 1000000000), random_int(1, 1000000000));

        $connection->socketId = $socketId;

        return $this;
    }
}
