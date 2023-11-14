<?php

namespace Laravel\Reverb\WebSockets;

use React\Stream\CompositeStream;
use Illuminate\Support\Str;
use React\Stream\DuplexStreamInterface;

class WsConnection
{
    public string $resourceId;

    public function __construct(public DuplexStreamInterface $stream)
    {
        $this->resourceId = (string) Str::uuid();
    }
}