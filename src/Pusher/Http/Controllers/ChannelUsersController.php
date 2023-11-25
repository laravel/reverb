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
    public function __invoke(RequestInterface $request, Connection $connection, string $channel, string $appId): Response
    {
        $this->verify($request, $connection, $appId);

        $channel = $this->channels->find($channel);

        if (! $channel instanceof PresenceChannel) {
            return new JsonResponse((object) [], 400);
        }

        $connections = collect($channel->connections())
            ->map(fn ($connection) => $connection->data())
            ->map(fn ($data) => ['id' => $data['user_id']])
            ->values();

        return new JsonResponse((object) ['users' => $connections]);
    }
}
