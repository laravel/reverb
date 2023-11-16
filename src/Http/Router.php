<?php

namespace Laravel\Reverb\Http;

use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Arr;
use Laravel\Reverb\WebSockets\WsConnection;
use Psr\Http\Message\RequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

class Router
{
    public function __construct(protected UrlMatcherInterface $matcher)
    {
    }

    /**
     * Dispatch the request to the appropriate controller.
     */
    public function dispatch(RequestInterface $request, Connection $connection): mixed
    {
        $uri = $request->getUri();
        $context = $this->matcher->getContext();
        $context->setMethod($request->getMethod());
        $context->setHost($uri->getHost());

        if ($this->isWebSocketRequest($request)) {
            $connection = $this->attemptUpgrade($request, $connection);
        }

        try {
            $route = $this->matcher->match($uri->getPath());
        } catch (MethodNotAllowedException $e) {
            // return $this->close($conn, 405, array('Allow' => $nae->getAllowedMethods()));
        } catch (ResourceNotFoundException $e) {
            // return $this->close($conn, 404);
        }

        return $route['_controller']($request, $connection, ...Arr::except($route, ['_controller', '_route']));
    }

    /**
     * Determine whether the request is for a WebSocket connection.
     */
    protected function isWebSocketRequest(RequestInterface $request): bool
    {
        return $request->getHeader('Upgrade')[0] ?? null === 'websocket';
    }

    /**
     * Negotiate the WebSocket connection upgrade.
     */
    protected function attemptUpgrade(RequestInterface $request, Connection $connection): WsConnection
    {
        $negotiator = new ServerNegotiator(new RequestVerifier);
        $response = $negotiator->handshake($request);

        $connection->write(Message::toString($response));

        return new WsConnection($connection);
    }
}
