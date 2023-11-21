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
    public function handle(RequestInterface $request, Connection $connection, ...$args): Response
    {
        if (! $connection = $this->channels->connections()[$args['user']]) {
            return new JsonResponse((object) [], 400);
        }

        $connection->connection()->disconnect();

        return new JsonResponse((object) []);
    }
}
