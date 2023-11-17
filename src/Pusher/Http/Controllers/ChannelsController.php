<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Laravel\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChannelsController extends Controller
{
    /**
     * Handle the request.
     */
    public function handle(RequestInterface $request, Connection $connection, ...$args): Response
    {
        $channels = $this->channels->channels()->mapWithKeys(fn ($connections, $name) => [$name => ['user_count' => count($connections)]]);

        return new JsonResponse((object) ['channels' => $channels]);
    }
}
