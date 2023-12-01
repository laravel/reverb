<?php

namespace Laravel\Reverb\Managers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Arr;
use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\Connection;

class CacheChannelManager implements ChannelManagerInterface
{
    use InteractsWithApplications;

    /**
     * The appliation instance.
     *
     * @var \Laravel\Reverb\Application
     */
    protected $application;

    public function __construct(
        protected Repository $repository,
        protected $prefix = 'reverb'
    ) {
        //
    }

    /**
     * Get the application instance.
     */
    public function app(): ?Application
    {
        return $this->application;
    }

    /**
     * Get all the channels.
     */
    public function all(): array
    {
        return $this->channels();
    }

    /**
     * Find the given channel
     */
    public function find(string $channel): Channel
    {
        return $this->channels($channel);
    }

    /**
     * Get all the connections for the given channels.
     *
     * @return array<string, \Laravel\Reverb\Servers\Reverb\ChannelConnection>
     */
    public function connections(string $channel = null): array
    {
        $channels = Arr::wrap($this->channels($channel));

        return array_reduce($channels, function ($carry, $channel) {
            return $carry + $channel->connections();
        }, []);
    }

    /**
     * Unsubscribe from all channels.
     */
    public function unsubscribeFromAll(Connection $connection): void
    {
        foreach ($this->channels() as $channel) {
            $channel->unsubscribe($connection);
        }
    }

    /**
     * Get the given channel.
     */
    public function channel(string $channel): Channel
    {
        return $this->channels($channel);
    }

    /**
     * Get the channels.
     *
     * @return \Laravel\Reverb\Channels\Channel|array<string, \Laravel\Reverb\Channels\Channel>
     */
    public function channels(string $channel = null): Channel|array
    {
        $channels = $this->repository->get($this->prefix, []);

        if(!isset($channels[$this->application->id()])){
            $channels[$this->application->id()] = [];
        }

        if ($channel) {
            if (! isset($channels[$this->application->id()][$channel])) {
                $channel = ChannelBroker::create($channel);
                $channels[$this->application->id()][$channel->name()] = serialize($channel);
                $this->repository->forever($this->prefix, $channels);

                return $channel;
            }

            return unserialize($channels[$this->application->id()][$channel]);
        }

        return array_map('unserialize', $channels[$this->application->id()] ?: []);
    }

    /**
     * Flush the channel manager repository.
     */
    public function flush(): void
    {
        $this->repository->forever($this->prefix, []);
    }
}
