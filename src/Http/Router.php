<?php

namespace Laravel\Reverb\Http;

use Closure;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Arr;
use Laravel\Reverb\Concerns\ClosesConnections;
use Laravel\Reverb\WebSockets\WsConnection;
use Psr\Http\Message\RequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use ReflectionFunction;
use ReflectionMethod;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

class Router
{
    use ClosesConnections;

    /**
     * The server negotiator.
     */
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

        $routeParameters = Arr::except($route, ['_controller', '_route']) + ['request' => $request, 'connection' => $connection];

        $response = $controller(
            ...$this->arguments($controller, $routeParameters)
        );

        return $connection->send($response)->close();
    }

    /**
     * Get the controller callable for the route.
     *
     * @param  array<string, mixed>  $route
     */
    protected function controller(array $route): callable
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

    /**
     * Find the arguments for the controller.
     *
     * @return array<int, mixed>
     */
    public function arguments(callable $controller, array $routeParameters): array
    {
        $parameters = $this->parameters($controller);

        return array_map(function ($parameter) use ($routeParameters) {
            return $routeParameters[$parameter['name']] ?? null;
        }, $parameters);
    }

    /**
     * Find the parameters for the controller.
     *
     * @return array<int, array{ name: string, type: string, position: int }>
     */
    public function parameters(mixed $controller): array
    {
        $method = match (true) {
            $controller instanceof Closure => new ReflectionFunction($controller),
            is_string($controller) => count($parts = explode('::', $controller)) > 1 ? new ReflectionMethod(...$parts) : new ReflectionFunction($controller),
            ! is_array($controller) => new ReflectionMethod($controller, '__invoke'),
            is_array($controller) => new ReflectionMethod($controller[0], $controller[1]),
        };

        $parameters = $method->getParameters();

        return array_map(function ($parameter) {
            return [
                'name' => $parameter->getName(),
                'type' => $parameter->getType()->getName(),
                'position' => $parameter->getPosition(),
            ];
        }, $parameters);
    }
}
