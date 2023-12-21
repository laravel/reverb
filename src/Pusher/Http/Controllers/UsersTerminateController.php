<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Laravel\Reverb\Servers\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UsersTerminateController extends Controller
{
    /**
     * Handle the request.
     */
    public function __invoke(RequestInterface $request, Connection $connection, string $appId, string $userId): Response
    {
        $this->verify($request, $connection, $appId);

        $connections = collect($this->channels->connections());

        $connections->each(function ($connection) use ($userId) {
            if ((string) $connection->data()['user_id'] === $userId) {
                $connection->disconnect();
            }
        });

        return new JsonResponse((object) []);
    }
}
