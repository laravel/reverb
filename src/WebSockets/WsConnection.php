<?php

namespace Laravel\Reverb\WebSockets;

use Evenement\EventEmitter;
use Laravel\Reverb\Http\Connection;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Message;
use Ratchet\RFC6455\Messaging\MessageBuffer;

class WsConnection extends EventEmitter
{
    protected $buffer;

    public function __construct(public Connection $connection)
    {
        $this->buffer = new MessageBuffer(
            new CloseFrameChecker,
            onMessage: fn (Message $message) => $this->emit('message', [$message->getPayload()]),
            onControl: fn () => $this->close(),
            sender: [$connection, 'send']
        );

        $connection->on('data', [$this->buffer, 'onData']);
        $connection->on('close', fn () => $this->emit('close'));
    }

    /**
     * Send a message to the connection.
     */
    public function send(string $message): void
    {
        $this->buffer->sendMessage($message);
    }

    /**
     * Close the connection.
     */
    public function close(): void
    {
        $this->connection->close();
    }

    public function id()
    {
        return $this->connection->id();
    }
}
