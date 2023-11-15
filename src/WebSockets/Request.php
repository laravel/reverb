<?php

namespace Laravel\Reverb\WebSockets;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\Http\Message\Response;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;

class Request
{
    protected $connection;

    protected $input;

    protected $output;

    protected $stream;

    protected $response;

    protected $startMemory;

    public function __construct(protected RequestInterface $request)
    {
        $this->startMemory = memory_get_usage();
        $negotiator = new ServerNegotiator(new RequestVerifier);
        dump(memory_get_usage() - $this->startMemory);
        $this->response = $negotiator->handshake($this->request);
        dump(memory_get_usage() - $this->startMemory);
    }

    /**
     * Determine whether thee request is a WebSocket request.
     */
    public function isWebSocketRequest(): bool
    {
        return $this->response->getStatusCode() === 101;
    }

    /**
     * Generate the response to the WebSocket request.
     */
    public function respond(): Response
    {
        return new Response(
            $this->response->getStatusCode(),
            $this->response->getHeaders(),
            new CompositeStream($this->output, $this->input)
        );
    }

    /**
     * Generate a WebSocket connection from the request.
     */
    public function connect(): WsConnection
    {
        $connection = new WsConnection($this->stream);
        dump(memory_get_usage() - $this->startMemory);

        return $connection;
    }
}
