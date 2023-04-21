<?php

namespace Laravel\Reverb\Servers\Swoole;

use Illuminate\Support\Facades\App;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as SwooleServer;

class Factory
{
    /**
     * Create a new Swoole WebSocket server instance.
     */
    public static function make(string $host = '0.0.0.0', string $port = '8080'): SwooleServer
    {
        $swoole = new SwooleServer($host, $port);
        $server = App::make(Server::class);

        $swoole->on('Open', fn (SwooleServer $swooleServer, Request $request) => $server->onOpen($swooleServer, $request));
        $swoole->on('Message', fn (SwooleServer $swooleServer, Frame $frame) => $server->onMessage($frame));
        $swoole->on('Close', fn (SwooleServer $swooleServer, string $identifier) => $server->onClose($identifier));
        $swoole->on('Disconnect', fn (SwooleServer $swooleServer, string $identifier) => $server->onClose($identifier));

        return $swoole;
    }
}
