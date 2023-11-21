<?php

namespace Laravel\Reverb\WebSockets;

use Evenement\EventEmitter;
use Laravel\Reverb\Http\Connection;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
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
            onControl: fn (FrameInterface $message) => $this->control($message),
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
     * Handle control frames.
     */
    public function control(FrameInterface $message): void
    {
        match ($message->getOpcode()) {
            Frame::OP_PING => $this->send(new Frame('pong', opcode: Frame::OP_PONG)),
            Frame::OP_CLOSE => $this->close(),
        };
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
