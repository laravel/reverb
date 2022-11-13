<?php

namespace Laravel\Reverb\Http\Controllers;

use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Laravel\Reverb\Event;
use Symfony\Component\HttpFoundation\JsonResponse;

class EventController implements HttpServerInterface
{
    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null)
    {
        Event::dispatch(
            (string) $request->getBody()
        );

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
