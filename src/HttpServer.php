<?php

namespace Laravel\Reverb;

use GuzzleHttp\Psr7\Message;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Servers\Reverb\Connection;
use Laravel\Reverb\WebSockets\WsConnection;
use Psr\Http\Message\RequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class HttpServer
{
    protected $loop;

    public function __construct(protected ServerInterface $socket, LoopInterface $loop = null)
    {
        $this->loop = $this->loop ?: Loop::get();

        $socket->on('connection', $this);
    }

    public function __invoke(ConnectionInterface $connection)
    {
        $connection = new Conn($connection);

        $connection->on('data', function ($data) use ($connection) {
            $this->handleRequest($data, $connection);
        });
        // $connection->on('close', function () use ($connection) {
        //     $this->handleEnd($conn);
        // });
        // $conn->on('error', function (\Exception $e) use ($conn) {
        //     $this->handleError($e, $conn);
        // });
    }

    public function start()
    {
        $this->loop->run();
    }

    protected function handleRequest(string $data, Conn $connection)
    {
        if (! $connection->isInitialized()) {
            $request = Request::from($data);
            $connection->initialize();

            if ($request->getUri()->getPath() === '/app/yysmuc8zbg4vo2hxgk9w') {
                $negotiator = new ServerNegotiator(new RequestVerifier);
                $response = $negotiator->handshake($request);

                $connection->write(Message::toString($response));

                $connection = new WsConnection($connection);

                $server = app(Server::class);
                $reverbConnection = $this->connection($request, $connection);
                $server->open($reverbConnection);

                $connection->on('message', fn (string $message) => $server->message($reverbConnection, $message));
                $connection->on('close', fn () => $server->close($reverbConnection));

                return;
            }

            $payload = json_decode($request->getBody()->getContents(), true);

            Event::dispatch($this->application($request), [
                'event' => $payload['name'] ?? 'subscribe',
                'channel' => $payload['channel'] ?? 'channel',
                'data' => $payload['data'] ?? [],
            ]);

            return tap($connection)->send(new JsonResponse((object) []))->close();
        }
    }

    protected function connection(RequestInterface $request, WsConnection $connection): Connection
    {
        return app(ConnectionManager::class)
            ->for($application = $this->application($request))
            ->connect(
                new Connection(
                    $connection,
                    $application,
                    $request->getHeader('Origin')[0] ?? null
                )
            );
    }

    /**
     * Get the application instance for the request.
     */
    protected function application(RequestInterface $request): Application
    {
        // parse_str($request->getUri()->getQuery(), $queryString);

        return app(ApplicationProvider::class)->findById('123456');
    }
}
