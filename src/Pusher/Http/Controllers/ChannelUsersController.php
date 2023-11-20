<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Laravel\Reverb\Channels\PresenceChannel;
use Laravel\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChannelUsersController extends Controller
{
    /**
     * Handle the request.
     */
    public function handle(RequestInterface $request, Connection $connection, ...$args): Response
    {
        $channel = $this->channels->find($args['channel']);

        if (! $channel instanceof PresenceChannel) {
            return new JsonResponse((object) [], 400);
        }

        return new JsonResponse((object) []);
    }
}
