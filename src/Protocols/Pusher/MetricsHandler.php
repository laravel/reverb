<?php

namespace Laravel\Reverb\Protocols\Pusher;

use Laravel\Reverb\Application;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\ServerProviderManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function React\Promise\Timer\timeout;

class MetricsHandler
{
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
    public function publish(Application $application, string $type, array $options = []): void
    {
        $this->pubSubProvider->publish([
            'type' => 'metrics-retrieved',
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
            default => [],
        };
    }

    /**
     * Get the connections for the given application.
     */
    public function connections(Application $application): array
    {
        return $this->channels->for($application)->connections();
    }

    /**
     * Return a promise to resolve the given value.
     */
    public static function promise(mixed $value): PromiseInterface
    {
        $deferred = new Deferred;
        $promise = $deferred->promise();
        $deferred->resolve($value);

        return $promise;
    }

    /**
     * Gather metrics from all subscribers for the given type.
     */
    protected function gatherMetrics(Application $application, string $type, array $options = []): PromiseInterface
    {
        [$metrics, $subscribers] = [[], null];

        $deferred = $this->listenForMetrics($metrics, $subscribers);
        $this->requestMetrics($application, $type, $options, $subscribers);

        return timeout($deferred->promise(), 5)->then(
            fn ($metrics) => $metrics,
            function () use (&$metrics) {
                return $metrics;
            }
        )->then(fn ($metrics) => $this->merge($metrics, $type));
    }

    /**
     * Listen for metrics from subscribers.
     */
    protected function listenForMetrics(array &$metrics, ?int &$subscribers): Deferred
    {
        $deferred = new Deferred;

        $this->pubSubProvider->on('metrics-retrieved', function ($payload) use (&$subscribers, &$metrics, $deferred) {
            $metrics[] = $payload;
            if ($subscribers !== null && count($metrics) === $subscribers) {
                $deferred->resolve($metrics);
            }
        });

        return $deferred;
    }

    /**
     * Request metrics from all subscribers.
     */
    protected function requestMetrics(Application $application, string $type, ?array $options, ?int &$subscribers): void
    {
        $this->pubSubProvider->publish([
            'type' => 'metrics',
            'application' => serialize($application),
            'payload' => ['type' => $type, 'options' => $options],
        ])->then(function ($total) use (&$subscribers) {
            $subscribers = $total;
        });
    }

    /**
     * Merge the metrics into a single result set.
     */
    public function merge(array $metrics, string $type): array
    {
        return match ($type) {
            'connections' => array_reduce($metrics, fn ($carry, $item) => array_merge($carry, $item['payload']), []),
            default => [],
        };
    }
}
