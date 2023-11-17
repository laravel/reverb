<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Event;
use Laravel\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EventsBatchController extends Controller
{
    /**
     * Handle the request.
     */
    public function handle(RequestInterface $request, Connection $connection, ...$args): Response
    {
        // @TODO Validate the request body as a JSON array of events in the correct format and a max of 10 items.
        
        $items = collect(json_decode($this->body, true));

        $info = $items->map(function ($item) {
            Event::dispatch(
                $this->application,
                [
                    'event' => $item['name'],
                    'channel' => $item['channel'],
                    'data' => $item['data'],
                ],
                isset($item['socket_id']) ? $this->connections->find($item['socket_id']) : null
            );

            return isset($item['info']) ? $this->getInfo($item['channel'], $item['info']) : [];
        });

        return $info->some(fn ($item) => count($item) > 0) ? new JsonResponse((object) ['batch' => $info->all()]) : new JsonResponse((object) []);
    }

    /**
     * Get the info for the given channels.
     *
     * @param  array<int, string>  $channels
     * @return array<string, array<string, int>>
     */
    protected function getInfo(string $channel, string $info): array
    {
        $info = explode(',', $info);
        $count = count($this->channels->connections(ChannelBroker::create($channel)));
        $info = [
            'user_count' => in_array('user_count', $info) ? $count : null,
            'subscription_count' => in_array('subscription_count', $info) ? $count : null,
        ];

        return array_filter($info, fn ($item) => $item !== null);
    }
}
