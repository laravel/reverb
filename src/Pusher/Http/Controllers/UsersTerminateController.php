<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Laravel\Reverb\Http\Connection;
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

        if (! $connection = $this->channels->connections()[$userId]) {
            return new JsonResponse((object) [], 400);
        }

        $connection->connection()->disconnect();

        return new JsonResponse((object) []);
    }
}
