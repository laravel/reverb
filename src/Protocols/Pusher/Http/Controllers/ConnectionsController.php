<?php

namespace Laravel\Reverb\Protocols\Pusher\Http\Controllers;

use Laravel\Reverb\Servers\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ConnectionsController extends Controller
{
    /**
     * Handle the request.
     */
    public function __invoke(RequestInterface $request, Connection $connection, string $appId): PromiseInterface
    {
        $this->verify($request, $connection, $appId);

        return $this->metrics('connections')
            ->then(fn ($connections) => new JsonResponse(['connections' => $connections]));
    }
}
