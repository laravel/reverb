<?php

namespace Laravel\Reverb\Managers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Arr;
use Laravel\Reverb\Application;
use Laravel\Reverb\Pusher\Channels\Channel;
use Laravel\Reverb\Pusher\Channels\ChannelBroker;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Pusher\Contracts\ChannelManager as ChannelManagerInterface;
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

    /**
     * Create a new cache channel manager instance.
     */
    public function __construct(protected Repository $repository, protected string $prefix = 'reverb')
    {
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
     *
     * @return array<string, \Laravel\Reverb\Channels\Channel>
     */
    public function all(): array
    {
        return $this->channels();
    }

    /**
     * Find the given channel
     */
    public function find(string $channel): ?Channel
    {
        return $this->channels($channel);
    }

    /**
     * Find the given channel or create it if it doesn't exist.
     */
    public function findOrCreate(string $channelName): Channel
    {
        if ($channel = $this->channels($channelName)) {
            return $channel;
        }

        $channels = $this->repository->get($this->prefix, []);
        $channel = ChannelBroker::create($channelName);
        $channels[$this->application->id()][$channel->name()] = serialize($channel);
        $this->repository->forever($this->prefix, $channels);

        return $channel;
    }

    /**
     * Get all the connections for the given channels.
     *
     * @return array<string, \Laravel\Reverb\Servers\Reverb\ChannelConnection>
     */
    public function connections(?string $channel = null): array
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
     * Remove the given channel.
     */
    public function remove(Channel $channel): void
    {
        $channels = $this->channels();

        unset($channels[$channel->name()]);

        $this->repository->forever($this->prefix, $channels);
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
    public function channels(?string $channel = null): Channel|array|null
    {
        $channels = $this->repository->get($this->prefix, []);

        if (! isset($channels[$this->application->id()])) {
            $channels[$this->application->id()] = [];
        }

        if ($channel) {
            return isset($channels[$this->application->id()][$channel])
                ? unserialize($channels[$this->application->id()][$channel])
                : null;
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
