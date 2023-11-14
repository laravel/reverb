<?php

namespace Laravel\Reverb\WebSockets;

use Evenement\EventEmitter;
use Illuminate\Support\Str;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Message;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use React\Stream\DuplexStreamInterface;

class WsConnection extends EventEmitter
{
    public string $resourceId;

    protected $buffer;

    public function __construct(public DuplexStreamInterface $stream)
    {
        $this->resourceId = (string) Str::uuid();

        $this->buffer = new MessageBuffer(
            new CloseFrameChecker,
            onMessage: fn (Message $message) => $this->emit('message', [$message->getPayload()]),
            onControl: fn () => $this->close(),
            sender: [$stream, 'write']
        );

        $stream->on('data', [$this->buffer, 'onData']);
        $stream->on('close', fn () => $this->emit('close'));
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
        $this->stream->close();
    }
}
