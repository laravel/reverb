<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Channels\Concerns\InteractsWithPresenceChannels;
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
    public function __invoke(RequestInterface $request, Connection $connection, string $appId): Response
    {
        $this->verify($request, $connection, $appId);

        $payload = json_decode($this->body, true);

        $validator = $this->validate($payload);

        if ($validator->fails()) {
            return new JsonResponse($validator->errors(), 422);
        }

        $channels = Arr::wrap($payload['channels'] ?? $payload['channel'] ?? []);

        Event::dispatch(
            $this->application,
            [
                'event' => $payload['name'],
                'channels' => $channels,
                'data' => $payload['data'],
            ],
            isset($payload['socket_id']) ? $this->channels->connections()[$payload['socket_id']]->connection() : null
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
            if (! $channel = $this->channels->find($channel)) {
                return [];
            }

            $count = count($channel->connections());

            $info = [
                'user_count' => in_array('user_count', $info) && $this->isPresenceChannel($channel) ? $count : null,
                'subscription_count' => in_array('subscription_count', $info) && ! $this->isPresenceChannel($channel) ? $count : null,
            ];

            return [$channel->name() => (object) array_filter($info, fn ($item) => $item !== null)];
        })->filter()->all();

        return ['channels' => $channels];
    }

    /**
     * Determine if the channel is a presence channel.
     */
    protected function isPresenceChannel(Channel $channel): bool
    {
        return in_array(InteractsWithPresenceChannels::class, class_uses($channel));
    }

    /**
     * Validate the incoming request.
     */
    protected function validate(array $payload): Validator
    {
        return ValidatorFacade::make($payload, [
            'name' => ['required', 'string'],
            'data' => ['required', 'array'],
            'channels' => ['required_without:channel', 'array'],
            'channel' => ['required_without:channels', 'string'],
            'socket_id' => ['string'],
            'info' => ['string'],
        ]);
    }
}
