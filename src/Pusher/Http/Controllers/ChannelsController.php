<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Illuminate\Support\Str;
use Laravel\Reverb\Http\Connection;
use Laravel\Reverb\Pusher\Concerns\InteractsWithChannelInformation;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChannelsController extends Controller
{
    use InteractsWithChannelInformation;

    /**
     * Handle the request.
     */
    public function __invoke(RequestInterface $request, Connection $connection, string $appId): Response
    {
        $this->verify($request, $connection, $appId);

        $channels = collect($this->channels->all());

        if (isset($this->query['filter_by_prefix'])) {
            $channels = $channels->filter(fn ($channel) => Str::startsWith($channel->name(), $this->query['filter_by_prefix']));
        }

        $channels = $channels->filter(fn ($channel) => count($channel->connections()) > 0);
        
        $channels = $this->infoForChannels(
            $channels->all(),
            $this->query['info'] ?? ''
        );

        return new JsonResponse([
            'channels' => array_map(fn ($item) => (object) $item, $channels)
        ]);
    }
}
