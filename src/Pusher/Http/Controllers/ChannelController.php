<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Laravel\Reverb\Servers\Reverb\Http\Connection;
use Laravel\Reverb\Pusher\Concerns\InteractsWithChannelInformation;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChannelController extends Controller
{
    use InteractsWithChannelInformation;

    /**
     * Handle the request.
     */
    public function __invoke(RequestInterface $request, Connection $connection, string $appId, string $channel): Response
    {
        $this->verify($request, $connection, $appId);

        return new JsonResponse((object) $this->info($channel, ($this->query['info'] ?? '').',occupied'));
    }
}
