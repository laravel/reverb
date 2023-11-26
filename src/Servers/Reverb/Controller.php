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
    public function __construct(protected Server $server, protected ApplicationProvider $applications)
    {
        //
    }

    /**
     * Invoke the Reverb WebSocket server.
     */
    public function __invoke(RequestInterface $request, WsConnection $connection, string $appKey): void
    {
        if (! $reverbConnection = $this->connection($request, $connection, $appKey)) {
            return;
        }

        $connection->onMessage(fn (string $message) => $this->server->message($reverbConnection, $message));
        $connection->onClose(fn () => $this->server->close($reverbConnection));
        $connection->openBuffer();

        $this->server->open($reverbConnection);
    }

    /**
     * Get the Reverb connection instance for the request.
     */
    protected function connection(RequestInterface $request, WsConnection $connection, string $key): ?ReverbConnection
    {
        try {
            $application = $this->applications->findByKey($key);
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
