<?php

namespace Laravel\Reverb\Http;

use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Arr;
use Laravel\Reverb\Concerns\ClosesConnections;
use Laravel\Reverb\WebSockets\WsConnection;
use Psr\Http\Message\RequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

class Router
{
    use ClosesConnections;

    protected ServerNegotiator $negotiator;

    public function __construct(protected UrlMatcherInterface $matcher)
    {
        $this->negotiator = new ServerNegotiator(new RequestVerifier);
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

        try {
            $route = $this->matcher->match($uri->getPath());
        } catch (MethodNotAllowedException $e) {
            return $this->close($connection, 405, 'Method now allowed', ['Allow' => $e->getAllowedMethods()]);
        } catch (ResourceNotFoundException $e) {
            return $this->close($connection, 404, 'Not found.');
        }

        $controller = $this->controller($route);

        if ($this->isWebSocketRequest($request)) {
            $wsConnection = $this->attemptUpgrade($request, $connection);
            return $controller($request, $wsConnection, ...Arr::except($route, ['_controller', '_route']));
        }

        $response = $controller($request, $connection, ...Arr::except($route, ['_controller', '_route']));

        return $connection->send($response)->close();
    }

    protected function controller($route): callable
    {
        return $route['_controller'];
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
        $response = $this->negotiator->handshake($request);

        $connection->write(Message::toString($response));

        return new WsConnection($connection);
    }
}
