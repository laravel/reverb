<?php

namespace Laravel\Reverb\Protocols\Pusher;

use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Loggers\Log;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionLimitExceeded;
use Laravel\Reverb\Protocols\Pusher\Exceptions\InvalidOrigin;
use Laravel\Reverb\Protocols\Pusher\Exceptions\PusherException;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Throwable;
use Laravel\Reverb\RateLimiting\RateLimitManager;
use Illuminate\Cache\RateLimiter;

class Server
{
    /**
     * Create a new server instance.
     */
    public function __construct(protected ChannelManager $channels, protected EventHandler $handler, protected RateLimitManager $rateLimitManager) 
    {
        //
    }

    /**
     * Handle the a client connection.
     */
    public function open(Connection $connection): void
    {
        try {
            $this->ensureWithinConnectionLimit($connection);
            $this->verifyOrigin($connection);

            $connection->touch();

            $this->handler->handle($connection, 'pusher:connection_established');

            Log::info('Connection Established', $connection->id());
        } catch (Exception $e) {
            $this->error($connection, $e);
        }
    }

    /**
     * Handle a new message received by the connected client.
     */
    public function message(Connection $from, string $message): void
    {
        Log::info('Message Received', $from->id());
        Log::message($message);

        $from->touch();

        try {
            $this->rateLimiter($from);

            $event = json_decode($message, associative: true, flags: JSON_THROW_ON_ERROR);

            Validator::make($event, ['event' => ['required', 'string']])->validate();

            match (Str::startsWith($event['event'], 'pusher:')) {
                true => $this->handler->handle(
                    $from,
                    $event['event'],
                    empty($event['data']) ? [] : $event['data'],
                ),
                default => ClientEvent::handle($from, $event)
            };

            Log::info('Message Handled', $from->id());

            MessageReceived::dispatch($from, $message);
        } catch (Throwable $e) {
            $this->error($from, $e);
        }
    }

    /**
     * Rate limit the incoming message.
     * 
     * @throws \Laravel\Reverb\Protocols\Pusher\Exceptions\RateLimitExceededException
     */
    protected function rateLimiter(Connection $from): void
    {
        if (!config('reverb.rate_limiting.enabled')) {
            return;
        }

        $this->rateLimitManager->handle($from);
    }

    /**
     * Handle a low-level WebSocket control frame.
     */
    public function control(Connection $from, FrameInterface $message): void
    {
        Log::info('Control Frame Received', $from->id());
        Log::message($message);

        $from->setUsesControlFrames();

        if (in_array($message->getOpcode(), [Frame::OP_PING, Frame::OP_PONG], strict: true)) {
            $from->touch();
        }
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

        Log::info('Connection Closed', $connection->id());
    }

    /**
     * Handle an error.
     */
    public function error(Connection $connection, Throwable $exception): void
    {
        if ($exception instanceof PusherException) {
            $connection->send(json_encode($exception->payload()));

            Log::error('Message from '.$connection->id().' resulted in a pusher error');
            Log::info($exception->getMessage());

            return;
        }

        $connection->send(json_encode([
            'event' => 'pusher:error',
            'data' => json_encode([
                'code' => 4200,
                'message' => 'Invalid message format',
            ]),
        ]));

        Log::error('Message from '.$connection->id().' resulted in an unknown error');
        Log::info($exception->getMessage());
    }

    /**
     * Ensure the server is within the connection limit.
     */
    protected function ensureWithinConnectionLimit(Connection $connection): void
    {
        if (! $connection->app()->hasMaxConnectionLimit()) {
            return;
        }

        $connections = $this->channels->for($connection->app())->connections();

        if (count($connections) >= $connection->app()->maxConnections()) {
            throw new ConnectionLimitExceeded;
        }
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

        foreach ($allowedOrigins as $allowedOrigin) {
            if (Str::is($allowedOrigin, $origin)) {
                return;
            }
        }

        throw new InvalidOrigin;
    }
}
