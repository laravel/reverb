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
        Pusher::handle($connection, 'pusher:connection_established');

        echo "New connection: ({$connection->id()})".PHP_EOL;
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
            Pusher::handle($from, $event['event'], $event['data'] ?? []);
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
}
