<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Laravel\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChannelController extends Controller
{
    /**
     * Handle the request.
     */
    public function __invoke(RequestInterface $request, Connection $connection, string $appId, string $channel): Response
    {
        $this->verify($request, $connection, $appId);

        $info = explode(',', $this->query['info'] ?? '');
        $connections = $this->channels->find($channel)->connections();
        $totalConnections = count($connections);

        return new JsonResponse((object) array_filter([
            'occupied' => $totalConnections > 0,
            'user_count' => in_array('user_count', $info) ? $totalConnections : null,
            'subscription_count' => in_array('subscription_count', $info) ? $totalConnections : null,
            'cache' => in_array('cache', $info) ? '{}' : null,
        ], fn ($item) => $item !== null));
    }
}
