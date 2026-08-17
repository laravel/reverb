<?php

use GuzzleHttp\Psr7\Request;
use Laravel\Reverb\Servers\Reverb\Connection as ReverbConnection;
use Laravel\Reverb\Servers\Reverb\Http\Connection;
use Laravel\Reverb\Servers\Reverb\Http\Route;
use Laravel\Reverb\Servers\Reverb\Http\Router;
use Laravel\Reverb\Tests\FakeRawSocketConnection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * Stand-in for a WebSocket-only controller (e.g. PusherController), which
 * type-hints the upgraded `Servers\Reverb\Connection`.
 */
class FakeWebSocketOnlyController
{
    public bool $invoked = false;

    public function __invoke(RequestInterface $request, ReverbConnection $connection, string $appKey): void
    {
        $this->invoked = true;
    }
}

/**
 * Stand-in for a plain HTTP controller, which type-hints the raw HTTP
 * connection and returns a response.
 */
class FakeHttpController
{
    public function __invoke(RequestInterface $request, Connection $connection, string $appId): string
    {
        return 'ok';
    }
}

function makeRouter(callable $controller, string $path = '/app/{appKey}'): Router
{
    $routes = new RouteCollection;
    $routes->add('test', Route::get($path, $controller));

    return new Router(new UrlMatcher($routes, new RequestContext));
}

it('does not treat a missing Upgrade header as a WebSocket request', function () {
    $router = makeRouter(new FakeWebSocketOnlyController);
    $isWebSocketRequest = new ReflectionMethod($router, 'isWebSocketRequest');

    $request = new Request('GET', '/app/reverb-key');

    expect($isWebSocketRequest->invoke($router, $request))->toBeFalse();
});

it('does not treat an unrelated Upgrade header value as a WebSocket request', function () {
    $router = makeRouter(new FakeWebSocketOnlyController);
    $isWebSocketRequest = new ReflectionMethod($router, 'isWebSocketRequest');

    // Previously, due to an operator-precedence bug, any non-null Upgrade
    // header value (not just "websocket") was treated as truthy here.
    $request = new Request('GET', '/app/reverb-key', ['Upgrade' => 'h2c']);

    expect($isWebSocketRequest->invoke($router, $request))->toBeFalse();
});

it('treats a genuine websocket Upgrade header as a WebSocket request', function () {
    $router = makeRouter(new FakeWebSocketOnlyController);
    $isWebSocketRequest = new ReflectionMethod($router, 'isWebSocketRequest');

    $request = new Request('GET', '/app/reverb-key', ['Upgrade' => 'websocket']);

    expect($isWebSocketRequest->invoke($router, $request))->toBeTrue();
});

it('returns a 426 response instead of crashing when a plain HTTP request hits a WebSocket-only controller', function () {
    $controller = new FakeWebSocketOnlyController;
    $router = makeRouter($controller);

    $rawConnection = new FakeRawSocketConnection;
    $connection = new Connection($rawConnection);

    // A plain, non-upgrade request (e.g. a health check, curl -I, or a
    // proxy that stripped the Upgrade header) hitting the WebSocket route.
    $request = new Request('GET', '/app/reverb-key');

    $router->dispatch($request, $connection);

    expect($controller->invoked)->toBeFalse();
    expect($rawConnection->ended)->toBeTrue();
    expect($rawConnection->written)->not->toBeEmpty();
    expect($rawConnection->written[0])->toContain('426');
    expect($rawConnection->written[0])->toContain('Upgrade: websocket');
});

it('still dispatches plain HTTP requests to plain HTTP controllers', function () {
    $router = makeRouter(new FakeHttpController, '/apps/{appId}');

    $rawConnection = new FakeRawSocketConnection;
    $connection = new Connection($rawConnection);

    $request = new Request('GET', '/apps/1234');

    $router->dispatch($request, $connection);

    expect($rawConnection->ended)->toBeTrue();
    expect($rawConnection->written)->not->toBeEmpty();
    expect($rawConnection->written[0])->toContain('ok');
});
