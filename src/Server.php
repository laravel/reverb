<?php

namespace Laravel\Reverb;

use Exception;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Exceptions\InvalidOrigin;
use Laravel\Reverb\Exceptions\PusherException;

class Server
{
    public function __construct(
        protected ConnectionManager $connections,
        protected ChannelManager $channels
    ) {
    }

    /**
     * Handle the a client connection.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @return void
     */
    public function open(Connection $connection)
    {
        try {
            $this->verifyOrigin($connection);

            PusherEvent::handle($connection, 'pusher:connection_established');

            Output::info('Connection Established', $connection->id());
        } catch (Exception $e) {
            $this->error($connection, $e);
        }
    }

    /**
     * Handle a new message received by the connected client.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @param  string  $message
     * @return void
     */
    public function message(Connection $from, string $message)
    {
        Output::info('Message Received', $from->id());
        Output::message($message);

        $event = json_decode($message, true);

        try {
            match (Str::startsWith($event['event'], 'pusher:')) {
                true => PusherEvent::handle(
                    $from,
                    $event['event'],
                    $event['data'] ?? [],
                    $event['channel'] ?? null
                ),
                default => ClientEvent::handle($from, $event)
            };

            Output::info('Message Handled', $from->id());
        } catch (PusherException $e) {
            $from->send(json_encode($e->payload()));
            $from->disconnect();

            Output::error('Message from '.$from->id().' resulted in a pusher error');
            Output::info($e->getMessage());
        } catch (Exception $e) {
            $from->send(json_encode([
                'event' => 'pusher:error',
                'data' => json_encode([
                    'code' => 4200,
                    'message' => 'Invalid message format',
                ]),
            ]));
            $from->disconnect();

            Output::error('Message from '.$from->id().' resulted in an unknown error');
            Output::info($e->getMessage());
        }
    }

    /**
     * Handle a client disconnection.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @return void
     */
    public function close(Connection $connection)
    {
        $this->channels
            ->for($connection->app())
            ->unsubscribeFromAll($connection);
        $this->connections->disconnect($connection->identifier());
        $connection->disconnect();

        Output::info('Connection Closed', $connection->id());
    }

    /**
     * Handle an error.
     *
     * @param  \Laravel\Reverb\ConnectionInterface  $connection
     * @param  \Exception  $exception
     * @return void
     */
    public function error(Connection $connection, Exception $exception)
    {
        if ($exception instanceof PusherException) {
            $connection->send(json_encode($exception->payload()));

            Output::error('Message from '.$connection->id().' resulted in a pusher error');

            return Output::info($exception->getMessage());
        }

        $connection->send(json_encode([
            'event' => 'pusher:error',
            'data' => json_encode([
                'code' => 4200,
                'message' => 'Invalid message format',
            ]),
        ]));

        Output::error('Message from '.$connection->id().' resulted in an unknown error');
        Output::info($exception->getMessage());
    }

    /**
     * Verify the origin of the connection.
     *
     * @param  \Laravel\Reverb\ConnectionInterface  $connection
     * @return void
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidOrigin
     */
    protected function verifyOrigin(Connection $connection)
    {
        $allowedOrigins = $connection->app()->allowedOrigins();

        if (in_array('*', $allowedOrigins)) {
            return;
        }

        $origin = parse_url($connection->origin(), PHP_URL_HOST);

        if (! $origin || ! in_array($origin, $allowedOrigins)) {
            throw new InvalidOrigin;
        }
    }
}
