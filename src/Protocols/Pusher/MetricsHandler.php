<?php

namespace Laravel\Reverb\Protocols\Pusher;

use Illuminate\Support\Str;
use Laravel\Reverb\Application;
use Laravel\Reverb\Protocols\Pusher\Concerns\InteractsWithChannelInformation;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\ServerProviderManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function React\Promise\Timer\timeout;

class MetricsHandler
{
    use InteractsWithChannelInformation;

    /**
     * The metrics being gathered.
     */
    protected array $metrics = [];

    /**
     * The total subscribers gathering metrics.
     *
     * @var array
     */
    protected ?int $subscribers = null;

    /**
     * Create an instance of the metrics handler.
     */
    public function __construct(
        protected ChannelManager $channels,
        protected ServerProviderManager $serverProviderManager,
        protected PubSubProvider $pubSubProvider
    ) {
        //
    }

    /**
     * Gather the metrics for the given type.
     */
    public function gather(Application $application, string $type, array $options = []): PromiseInterface
    {
        if ($this->serverProviderManager->subscribesToEvents()) {
            return $this->gatherMetrics($application, $type, $options);
        }

        return $this->promise($this->get($application, $type, $options));
    }

    /**
     * Publish the metrics for the given type.
     */
    public function publish(Application $application, string $key, string $type, array $options = []): void
    {
        $this->pubSubProvider->publish([
            'type' => 'metrics-retrieved',
            'key' => $key,
            'application' => serialize($application),
            'payload' => $this->get($application, $type, $options),
        ]);
    }

    /**
     * Get the metrics for the given type.
     */
    public function get(Application $application, string $type, array $options): array
    {
        return match ($type) {
            'connections' => $this->connections($application),
            'channels' => $this->channels($application, $options),
            'channel' => $this->channel($application, $options),
            'channel_users' => $this->channelUsers($application, $options),
            default => [],
        };
    }

    /**
     * Return a promise to resolve the given value.
     */
    protected function promise(mixed $value): PromiseInterface
    {
        $deferred = new Deferred;
        $promise = $deferred->promise();
        $deferred->resolve($value);

        return $promise;
    }

    /**
     * Get the connections for the given application.
     */
    protected function connections(Application $application): array
    {
        return $this->channels->for($application)->connections();
    }

    /**
     * Get the channels for the given application.
     */
    protected function channels(Application $application, array $options): array
    {
        if (isset($options['channels'])) {
            return $this->infoForChannels($application, $options['channels'], $options['info'] ?? '');
        }

        $channels = collect($this->channels->for($application)->all());

        if ($filter = ($options['filter'] ?? false)) {
            $channels = $channels->filter(fn ($channel) => Str::startsWith($channel->name(), $filter));
        }

        $channels = $channels->filter(fn ($channel) => count($channel->connections()) > 0);

        return $this->infoForChannels(
            $application,
            $channels->all(),
            $options['info'] ?? ''
        );
    }

    /**
     * Get the channel for the given application.
     */
    protected function channel(Application $application, array $options): array
    {
        return $this->info($application, $options['channel'], $options['info'] ?? '');
    }

    protected function channelUsers(Application $application, array $options): array
    {
        $channel = $this->channels->for($application)->find($options['channel']);

        if (! $channel) {
            return [];
        }

        return collect($channel->connections())
            ->map(fn ($connection) => $connection->data())
            ->map(fn ($data) => ['id' => $data['user_id']])
            ->values()
            ->all();
    }

    /**
     * Gather metrics from all subscribers for the given type.
     */
    protected function gatherMetrics(Application $application, string $type, array $options = []): PromiseInterface
    {
        $deferred = $this->listenForMetrics($key = Str::random(10));
        $this->requestMetrics($application, $key, $type, $options);

        return timeout($deferred->promise(), 5)->then(
            fn ($metrics) => $metrics,
            function () {
                return $this->metrics;
            }
        )->then(fn ($metrics) => $this->merge($metrics, $type));
    }

    /**
     * Listen for metrics from subscribers.
     */
    protected function listenForMetrics(string $key): Deferred
    {
        $deferred = new Deferred;

        $this->pubSubProvider->on('metrics-retrieved', function ($payload) use ($key, $deferred) {
            if ($payload['key'] !== $key) {
                return;
            }

            $this->metrics[] = $payload['payload'];

            if ($this->subscribers !== null && count($this->metrics) === $this->subscribers) {
                $deferred->resolve($this->metrics);
            }
        });

        return $deferred;
    }

    /**
     * Request metrics from all subscribers.
     */
    protected function requestMetrics(Application $application, string $key, string $type, ?array $options): void
    {
        $this->pubSubProvider->publish([
            'type' => 'metrics',
            'key' => $key,
            'application' => serialize($application),
            'payload' => ['type' => $type, 'options' => $options],
        ])->then(function ($total) {
            $this->subscribers = $total;
        });
    }

    /**
     * Merge the metrics into a single result set.
     */
    protected function merge(array $metrics, string $type): array
    {
        return match ($type) {
            'connections' => array_reduce($metrics, fn ($carry, $item) => array_merge($carry, $item), []),
            'channels' => $this->mergeChannels($metrics),
            'channel' => $this->mergeChannel($metrics),
            'channel_users' => collect($metrics)->flatten(1)->unique()->all(),
            default => [],
        };
    }

    /**
     * Merge multiple sets of channel metrics into a single result set.
     */
    protected function mergeChannels(array $metrics): array
    {
        return collect($metrics)
            ->reduce(function ($carry, $item) {
                collect($item)->each(function ($data, $channel) use ($carry) {
                    $metrics = $carry->get($channel, []);
                    $metrics[] = $data;
                    $carry->put($channel, $metrics);
                });

                return $carry;
            }, collect())
            ->map(fn ($metrics) => $this->mergeChannel($metrics))
            ->all();
    }

    /**
     * Merge multiple channels into a single set.
     */
    protected function mergeChannel(array $metrics): array
    {
        return collect($metrics)
            ->reduce(function ($carry, $item) {
                collect($item)->each(fn ($value, $key) => $carry->put($key, match ($key) {
                    'occupied' => $carry->get($key, false) || $value,
                    'user_count' => $carry->get($key, 0) + $value,
                    'subscription_count' => $carry->get($key, 0) + $value,
                    default => $value,
                }));

                return $carry;
            }, collect())
            ->all();
    }
}
