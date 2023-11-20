<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Illuminate\Support\Arr;
use Laravel\Reverb\Event;
use Laravel\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EventsController extends Controller
{
    /**
     * Handle the request.
     */
    public function handle(RequestInterface $request, Connection $connection, ...$args): Response
    {
        // @TODO Validate the request body as a JSON object in the correct format.

        $payload = json_decode($this->body, true);
        $channels = Arr::wrap($payload['channels'] ?? $payload['channel'] ?? []);

        Event::dispatch(
            $this->application,
            [
                'event' => $payload['name'],
                'channels' => $channels,
                'data' => $payload['data'],
            ],
            isset($payload['socket_id']) ? $this->connections->find($payload['socket_id']) : null
        );

        if (isset($payload['info'])) {
            return new JsonResponse((object) $this->getInfo($channels, $payload['info']));
        }

        return new JsonResponse((object) []);
    }

    /**
     * Get the info for the given channels.
     *
     * @param  array<int, string>  $channels
     * @return array<string, array<string, int>>
     */
    protected function getInfo(array $channels, string $info): array
    {
        $info = explode(',', $info);

        $channels = collect($channels)->mapWithKeys(function ($channel) use ($info) {
            $count = count($this->channels->find($channel)->connections());
            $info = [
                'user_count' => in_array('user_count', $info) ? $count : null,
                'subscription_count' => in_array('subscription_count', $info) ? $count : null,
            ];

            return [$channel => array_filter($info, fn ($item) => $item !== null)];
        })->all();

        return ['channels' => $channels];
    }
}
