<?php

namespace Laravel\Reverb\Protocols\Pusher\Managers;

use Illuminate\Support\Arr;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelBroker;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager as ChannelManagerInterface;

class ArrayChannelManager implements ChannelManagerInterface
{
    use InteractsWithApplications;

    /**
     * The underlying array of applications and their channels.
     *
     * @var array<string, array<string, Channel>>
     */
    protected array $applications = [];

    /**
     * The application instance.
     *
     * @var Application|null
     */
    protected ?Application $application = null;

    /**
     * Get the application instance.
     */
    public function app(): ?Application
    {
        return $this->application;
    }

    /**
     * Get all channels for the current application.
     *
     * @return array<string, Channel>
     */
    public function all(): array
    {
        return $this->channels();
    }

    /**
     * Check if the given channel exists.
     */
    public function exists(string $channel): bool
    {
        return isset($this->applications[$this->application->id()][$channel]);
    }

    /**
     * Find a specific channel.
     */
    public function find(string $channel): ?Channel
    {
        return $this->channels($channel);
    }

    /**
     * Find or create a channel by name.
     */
    public function findOrCreate(string $channelName): Channel
    {
        return $this->find($channelName) ?? $this->createChannel($channelName);
    }

    /**
     * Create a new channel and dispatch an event.
     */
    protected function createChannel(string $channelName): Channel
    {
        $channel = ChannelBroker::create($channelName);

        $this->applications[$this->application->id()][$channel->name()] = $channel;

        ChannelCreated::dispatch($channel);

        return $channel;
    }

    /**
     * Get all connections for a specific channel or all channels.
     *
     * @return array<string, \Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection>
     */
    public function connections(?string $channel = null): array
    {
        $channels = Arr::wrap($this->channels($channel));

        return array_reduce($channels, fn ($carry, $channel) => $carry + $channel->connections(), []);
    }

    /**
     * Unsubscribe a connection from all channels.
     */
    public function unsubscribeFromAll(Connection $connection): void
    {
        foreach ($this->channels() as $channel) {
            $channel->unsubscribe($connection);
        }
    }

    /**
     * Remove a specific channel and dispatch an event.
     */
    public function remove(Channel $channel): void
    {
        unset($this->applications[$this->application->id()][$channel->name()]);

        ChannelRemoved::dispatch($channel);
    }

    /**
     * Get a specific channel or all channels for the application.
     *
     * @return Channel|array<string, Channel>|null
     */
    public function channels(?string $channel = null): Channel|array|null
    {
        $channels = $this->applications[$this->application->id()] ?? [];

        return $channel ? ($channels[$channel] ?? null) : $channels;
    }

    /**
     * Flush all channels for all applications.
     */
    public function flush(): void
    {
        app(ApplicationProvider::class)
            ->all()
            ->each(fn (Application $application) => $this->applications[$application->id()] = []);
    }
}
