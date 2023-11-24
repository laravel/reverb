<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Illuminate\Support\Str;
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
        $channels = collect($this->channels->all());
        $info = explode(',', $this->query['info'] ?? '');

        if (isset($this->query['filter_by_prefix'])) {
            $channels = $channels->filter(fn ($channel) => Str::startsWith($channel->name(), $this->query['filter_by_prefix']));
        }

        $channels = $channels->mapWithKeys(function ($channel) use ($info) {
            return [$channel->name() => array_filter(['user_count' => in_array('user_count', $info) ? count($channel->connections()) : null])];
        });

        return new JsonResponse((object) ['channels' => $channels]);
    }
}
