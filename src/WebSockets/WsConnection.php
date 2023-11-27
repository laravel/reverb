<?php

namespace Laravel\Reverb\WebSockets;

use Evenement\EventEmitter;
use Laravel\Reverb\Http\Connection;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\DataInterface;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\MessageBuffer;

class WsConnection extends EventEmitter
{
    /**
     * The message buffer.
     *
     * @var \Ratchet\RFC6455\Messaging\MessageBuffer
     */
    protected $buffer;

    /**
     * The message handler.
     *
     * @var ?callable
     */
    protected $onMessage;

    /**
     * The connection close handler.
     *
     * @var ?callable
     */
    protected $onClose;

    /**
     * The maximum number of allowed bytes for each message.
     *
     * @var int
     */
    protected $maxMessageSize;

    public function __construct(public Connection $connection)
    {
        //
    }

    /**
     * Undocumented function
     */
    public function openBuffer(): void
    {
        $this->buffer = new MessageBuffer(
            new CloseFrameChecker,
            maxMessagePayloadSize: $this->maxMessageSize,
            onMessage: $this->onMessage ?: fn () => null,
            onControl: fn (FrameInterface $message) => $this->control($message),
            sender: [$this->connection, 'send']
        );

        $this->connection->on('data', [$this->buffer, 'onData']);
        $this->connection->on('close', $this->onClose ?: fn () => null);
    }

    /**
     * Send a message to the connection.
     */
    public function send(mixed $message): void
    {
        $this->connection->send(
            $message instanceof DataInterface ?
                $message->getContents() :
                (new Frame($message))->getContents()
        );
    }

    /**
     * Handle control frames.
     */
    public function control(FrameInterface $message): void
    {
        match ($message->getOpcode()) {
            Frame::OP_PING => $this->send(new Frame($message->getPayload(), opcode: Frame::OP_PONG)),
            Frame::OP_PONG => fn () => null,
            Frame::OP_CLOSE => $this->close($message),
        };
    }

    /**
     * Set the message handler.
     */
    public function onMessage(callable $callback): void
    {
        $this->onMessage = $callback;
    }

    /**
     * Set the close handler.
     */
    public function onClose(callable $callback): void
    {
        $this->onClose = $callback;
    }

    /**
     * Set the maximum number of allowed bytes for each message from the client.
     */
    public function withMaxMessageSize(int $size): void
    {
        $this->maxMessageSize = $size;
    }

    /**
     * Close the connection.
     */
    public function close(FrameInterface $frame = null): void
    {
        if ($frame) {
            $this->send($frame);
        }

        $this->connection->close();
    }

    /**
     * Get the raw socket connection identifier.
     */
    public function id()
    {
        return $this->connection->id();
    }
}
