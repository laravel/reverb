<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Laravel\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ChannelsController extends Controller
{
    /**
     * Handle the request.
     */
    public function handle(RequestInterface $request, Connection $connection, ...$args)
    {
        return new JsonResponse((object) []);
    }
}
