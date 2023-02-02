<?php

namespace Laravel\Reverb\Http\Controllers;

use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ApplicationsProvider;
use Laravel\Reverb\Event;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class EventController implements HttpServerInterface
{
    /**
     * Handle the a client connection.
     */
    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null): void
    {
        $payload = json_decode($request->getBody()->getContents(), true);

        Event::dispatch($this->application($request), [
            'event' => $payload['name'],
            'channel' => $payload['channel'],
            'data' => $payload['data'],
        ]);

        tap($conn)->send(new JsonResponse((object) []))->close();
    }

    /**
     * Handle a new message received by the connected client.
     *
     * @param  string  $message
     */
    public function onMessage(ConnectionInterface $from, $message): void
    {
        //
    }

    /**
     * Handle a client disconnection.
     */
    public function onClose(ConnectionInterface $connection): void
    {
        //
    }

    /**
     * Handle an error.
     */
    public function onError(ConnectionInterface $connection, \Exception $e): void
    {
        //
    }

    /**
     * Get the application instance for the request.
     */
    protected function application(RequestInterface $request): Application
    {
        parse_str($request->getUri()->getQuery(), $queryString);

        return app(ApplicationsProvider::class)->findById($queryString['appId']);
    }
}
