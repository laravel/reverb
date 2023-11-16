<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use GuzzleHttp\Psr7\Response;
use Laravel\Reverb\Event;
use Laravel\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class EventsController extends Controller
{
    /**
     * Handle the request.
     */
    public function handle(RequestInterface $request, Connection $connection, ...$args)
    {
        $payload = json_decode($this->body, true);

        Event::dispatch($this->application, [
            'event' => $payload['name'],
            'channel' => $payload['channel'],
            'data' => $payload['data'],
        ]);

        return new JsonResponse((object) []);
    }
}