<?php

namespace Laravel\Reverb\Pusher;

use Exception;
use Illuminate\Support\Str;
use Laravel\Reverb\ClientEvent;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Exceptions\InvalidOrigin;
use Laravel\Reverb\Exceptions\PusherException;
use Laravel\Reverb\Pusher\Event as PusherEvent;

class Server
{
    public function __construct(protected ChannelManager $channels, protected PusherEvent $pusher)
    {
        //
    }

    /**
     * Handle the a client connection.
     */
    public function open(Connection $connection): void
    {
        try {
            $this->verifyOrigin($connection);

            $connection->touch();

            $this->pusher->handle($connection, 'pusher:connection_established');
        } catch (Exception $e) {
            $this->error($connection, $e);
        }
    }

    /**
     * Handle a new message received by the connected client.
     */
    public function message(Connection $from, string $message): void
    {
        $event = json_decode($message, true);

        try {
            match (Str::startsWith($event['event'], 'pusher:')) {
                true => $this->pusher->handle(
                    $from,
                    $event['event'],
                    $event['data'] ?? [],
                    $event['channel'] ?? null
                ),
                default => ClientEvent::handle($from, $event)
            };

        } catch (Exception $e) {
            $this->error($from, $e);
        }

        $from->touch();
    }

    /**
     * Handle a client disconnection.
     */
    public function close(Connection $connection): void
    {
        $this->channels
            ->for($connection->app())
            ->unsubscribeFromAll($connection);

        $connection->disconnect();
    }

    /**
     * Handle an error.
     */
    public function error(Connection $connection, Exception $exception): void
    {
        if ($exception instanceof PusherException) {
            $connection->send(json_encode($exception->payload()));

            return;
        }

        $connection->send(json_encode([
            'event' => 'pusher:error',
            'data' => json_encode([
                'code' => 4200,
                'message' => 'Invalid message format',
            ]),
        ]));
    }

    /**
     * Verify the origin of the connection.
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidOrigin
     */
    protected function verifyOrigin(Connection $connection): void
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
