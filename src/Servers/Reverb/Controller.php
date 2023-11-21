<?php

namespace Laravel\Reverb\Servers\Reverb;

use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Exceptions\InvalidApplication;
use Laravel\Reverb\Server;
use Laravel\Reverb\Servers\Reverb\Connection as ReverbConnection;
use Laravel\Reverb\WebSockets\WsConnection;
use Psr\Http\Message\RequestInterface;

class Controller
{
    /**
     * Invoke the Reverb WebSocket server.
     */
    public function __invoke(RequestInterface $request, WsConnection $connection, string $appKey): void
    {
        if (! $reverbConnection = $this->connection($request, $connection, $appKey)) {
            return;
        }

        $server = app(Server::class);
        $server->open($reverbConnection);

        $connection->on('message', fn (string $message) => $server->message($reverbConnection, $message));
        $connection->on('close', fn () => $server->close($reverbConnection));
    }

    /**
     * Get the Reverb connection instance for the request.
     */
    protected function connection(RequestInterface $request, WsConnection $connection, string $key): ?ReverbConnection
    {
        try {
            $application = app(ApplicationProvider::class)->findByKey($key);
        } catch (InvalidApplication $e) {
            $connection->send('{"event":"pusher:error","data":"{\"code\":4001,\"message\":\"Application does not exist\"}"}');

            return $connection->close();
        }

        return new ReverbConnection(
            $connection,
            $application,
            $request->getHeader('Origin')[0] ?? null
        );
    }
}
