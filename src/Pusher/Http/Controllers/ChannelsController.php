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
        $channels = $this->channels->channels();
        $info = explode(',', $this->query['info'] ?? '');

        if (isset($this->query['filter_by_prefix'])) {
            $channels = $channels->filter(fn ($connections, $name) => Str::startsWith($name, $this->query['filter_by_prefix']));
        }

        $channels = $channels->mapWithKeys(function ($connections, $name) use ($info) {
            return [$name => array_filter(['user_count' => in_array('user_count', $info) ? count($connections) : null])];
        });

        return new JsonResponse((object) ['channels' => $channels]);
    }
}
