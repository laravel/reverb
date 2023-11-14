<?php

namespace Laravel\Reverb\WebSockets;

use Illuminate\Support\Facades\App;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\Http\Message\Response;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;

class Request
{
    protected $connection;

    public function __construct(protected ServerRequestInterface $request)
    {

    }

    public function isWebSocketRequest()
    {
        $upgrade = $this->request->getHeader('Upgrade')[0] ?? null;

        return $upgrade === 'websocket';
    }

    public function negotiate()
    {
        $negotiator = new ServerNegotiator(new RequestVerifier);
        $response = $negotiator->handshake($this->request);

        if ($response->getStatusCode() != '101') {
            return false;
        }

        $inStream = new ThroughStream();
        $outStream = new ThroughStream();

        $this->connect($inStream, $outStream);

        return new Response(
            $response->getStatusCode(),
            $response->getHeaders(),
            new CompositeStream($outStream, $inStream)
        );
    }

    public function connect($inStream, $outStream)
    {
        return $this->connection = Connection::make(
            new WsConnection(new CompositeStream($inStream, $outStream)),
            $this->application(),
            $this->origin(),
        );
    }

    public function connection()
    {
        return $this->connection;
    }

    protected function application()
    {
        parse_str($this->request->getUri()->getQuery(), $queryString);

        return App::make(ApplicationProvider::class)->findByKey($queryString['appId']);
    }

    protected function origin()
    {
        return $this->request->getHeader('Origin')[0] ?? null;
    }
}
