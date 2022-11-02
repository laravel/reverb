<?php

namespace Reverb;

use Exception;
use Illuminate\Support\Str;
use Reverb\Contracts\ConnectionManager;

class Server
{
    public function __construct(protected ConnectionManager $manager)
    {
    }

    /**
     * Handle the a client connection.
     *
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function open(Connection $connection)
    {
        $this->manager->add($connection);

        echo "New connection: ({$connection->id()})".PHP_EOL;

        $connection->send(json_encode([
            'event' => 'pusher:connection_established',
            'data' => json_encode([
                'socket_id' => $connection->id(),
                'activity_timeout' => 30,
            ]),
        ]));
    }

    /**
     * Handle a new message received by the connected client.
     *
     * @param  \Reverb\Connection  $connection
     * @param  string  $message
     * @return void
     */
    public function message(Connection $from, string $message)
    {
        $event = json_decode($message, true);

        if (Str::contains($event['event'], 'pusher:')) {
            $from->send($this->handlePusherMessage($event, $from));
        }

        echo 'Message from '.$from->id().': '.$message.PHP_EOL;
    }

    /**
     * Handle a client disconnection.
     *
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function close(Connection $connection)
    {
        echo "Connection {$connection->id()} has disconnected".PHP_EOL;
    }

    /**
     * Handle an error.
     *
     * @param  \Reverb\ConnectionInterface  $connection
     * @param  \Exception  $e
     * @return void
     */
    public function error(Connection $connection, Exception $e)
    {
        echo 'Error: '.$e->getMessage().PHP_EOL;
    }

    /**
     * Handle a Pusher protocol message.
     *
     * @param  array  $event
     * @param  \Reverb\Connection  $connection
     * @return string
     */
    protected function handlePusherMessage(array $event, Connection $connection)
    {
        if ($event['event'] === 'pusher:ping') {
            return json_encode([
                'event' => 'pusher:pong',
                'data' => json_encode([
                    'socket_id' => $connection->id(),
                ]),
            ]);
        }
    }
}
