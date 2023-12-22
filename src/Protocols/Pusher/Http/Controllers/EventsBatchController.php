<?php

namespace Laravel\Reverb\Protocols\Pusher\Http\Controllers;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Laravel\Reverb\Protocols\Pusher\Concerns\InteractsWithChannelInformation;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Laravel\Reverb\Servers\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EventsBatchController extends Controller
{
    use InteractsWithChannelInformation;

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

        $items = collect($payload['batch']);

        $info = $items->map(function ($item) {
            EventDispatcher::dispatch(
                $this->application,
                [
                    'event' => $item['name'],
                    'channel' => $item['channel'],
                    'data' => $item['data'],
                ],
                isset($item['socket_id']) ? ($this->channels->connections()[$item['socket_id']] ?? null) : null
            );

            return isset($item['info']) ? $this->info($item['channel'], $item['info']) : [];
        });

        if ($info->some(fn ($item) => count($item) > 0)) {
            return new JsonResponse(
                ['batch' => $info->map(fn ($item) => (object) $item)->all()]
            );
        }

        return new JsonResponse(['batch' => (object) []]);
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
        $count = count($this->channels->find($channel)->connections());
        $info = [
            'user_count' => in_array('user_count', $info) ? $count : null,
            'subscription_count' => in_array('subscription_count', $info) ? $count : null,
        ];

        return array_filter($info, fn ($item) => $item !== null);
    }

    /**
     * Validate the incoming request.
     */
    protected function validate(array $payload): Validator
    {
        return ValidatorFacade::make($payload, [
            'batch' => ['required', 'array'],
            'batch.*.name' => ['required', 'string'],
            'batch.*.data' => ['required', 'string'],
            'batch.*.channel' => ['required_without:channels', 'string'],
            'batch.*.socket_id' => ['string'],
            'batch.*.info' => ['string'],
        ]);
    }
}
