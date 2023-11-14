<?php

namespace Laravel\Reverb\WebSockets;

use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\GeneratesPusherIdentifiers;
use Laravel\Reverb\Connection as ReverbConnection;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Server;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Message;
use Ratchet\RFC6455\Messaging\MessageBuffer;

class Connection extends ReverbConnection
{
    use GeneratesPusherIdentifiers;

    /**
     * The normalized socket ID.
     *
     * @var string
     */
    protected $id;

    protected $buffer;

    public function __construct(protected WsConnection $connection, Application $application, string $origin = null)
    {
        parent::__construct($application, $origin);

        $this->buffer = new MessageBuffer(
            new CloseFrameChecker,
            onMessage: function (Message $message) {
                App::make(Server::class)->message($this, $message->getPayload());
            },
            sender: [$connection->stream, 'write']
        );

        App::make(ConnectionManager::class)->for($application)->resolve(
            $connection->resourceId,
            fn () => $this
        );
        
        App::make(Server::class)->open($this);
        $connection->stream->on('data', [$this->buffer, 'onData']);
        $connection->stream->on('close', function () {
            App::make(Server::class)->close($this);
        });
    }

    /**
     * Get the raw socket connection identifier.
     */
    public function identifier(): string
    {
        return (string) $this->connection->resourceId;
    }

    /**
     * Get the normalized socket ID.
     */
    public function id(): string
    {
        if (! $this->id) {
            $this->id = $this->generateId();
        }

        return $this->id;
    }

    public static function make(WsConnection $connection, $application, $origin)
    {
        return new static($connection, $application, $origin);
    }

    public function send(string $message): void
    {
        $this->buffer->sendMessage($message);
    }

    public function terminate(): void
    {
        $this->connection->stream->close();
    }
}
