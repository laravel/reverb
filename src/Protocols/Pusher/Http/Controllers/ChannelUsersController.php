<?php

namespace Laravel\Reverb\Protocols\Pusher\Http\Controllers;

use Laravel\Reverb\Protocols\Pusher\Concerns\InteractsWithChannelInformation;
use Laravel\Reverb\Protocols\Pusher\MetricsHandler;
use Laravel\Reverb\Servers\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChannelUsersController extends Controller
{
    use InteractsWithChannelInformation;

    /**
     * Handle the request.
     */
    public function __invoke(RequestInterface $request, Connection $connection, string $channel, string $appId): Response|PromiseInterface
    {
        $this->verify($request, $connection, $appId);

        $channel = $this->channels->find($channel);

        if (! $channel) {
            return new JsonResponse((object) [], 404);
        }

        if (! $this->isPresenceChannel($channel)) {
            return new JsonResponse((object) [], 400);
        }

        return app(MetricsHandler::class)
            ->gather($this->application, 'channel_users', ['channel' => $channel->name()])
            ->then(fn ($connections) => new JsonResponse(['users' => $connections]));
    }
}
