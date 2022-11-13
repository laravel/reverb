<?php

namespace Laravel\Reverb;

use Exception;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Exceptions\PusherException;

class Server
{
    public function __construct(protected ConnectionManager $connections, protected ChannelManager $channels)
    {
    }

    /**
     * Handle the a client connection.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
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
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  string  $message
     * @return void
     */
    public function message(Connection $from, string $message)
    {
        echo 'Message from '.$from->id().': '.$message.PHP_EOL;

        $event = json_decode($message, true);

        try {
            Pusher::handle($from, $event['event'], $event['data'] ?? []);

            echo 'Message from '.$from->id().' handled'.PHP_EOL;
        } catch (PusherException $e) {
            $from->send(json_encode($e->payload()));

            echo 'Message from '.$from->id().' resulted in a pusher error'.PHP_EOL;
        } catch (Exception $e) {
            $from->send(json_encode([
                'event' => 'pusher:error',
                'data' => json_encode([
                    'code' => 4200,
                    'message' => 'Invalid message format',
                ]),
            ]));

            echo 'Message from '.$from->id().' resulted in an unknown error'.PHP_EOL;
            echo $e->getMessage().PHP_EOL;
        }
    }

    /**
     * Handle a client disconnection.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return void
     */
    public function close(Connection $connection)
    {
        $this->connections->disconnect($connection);
        $this->channels->unsubscribeFromAll($connection);

        echo "Disconnected: ({$connection->id()})".PHP_EOL;
    }

    /**
     * Handle an error.
     *
     * @param  \Laravel\Reverb\Contracts\ConnectionInterface  $connection
     * @param  \Exception  $e
     * @return void
     */
    public function error(Connection $connection, Exception $e)
    {
        echo 'Error: '.$e->getMessage().PHP_EOL;
    }
}
