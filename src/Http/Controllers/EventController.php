<?php

namespace Laravel\Reverb\Http\Controllers;

use Laravel\Reverb\Event;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class EventController implements HttpServerInterface
{
    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null)
    {
        $payload = json_decode($request->getBody()->getContents(), true);

        Event::dispatch([
            'event' => $payload['name'],
            'channel' => $payload['channel'],
            'data' => $payload['data'],
        ]);

        tap($conn)->send(new JsonResponse((object) []))->close();
    }

    public function onMessage(ConnectionInterface $from, $message)
    {
        //
    }

    public function onClose(ConnectionInterface $connection)
    {
        //
    }

    public function onError(ConnectionInterface $connection, \Exception $e)
    {
        //
    }
}
