<?php

namespace Laravel\Reverb\Managers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\Connection;

class ArrayChannelManager implements ChannelManagerInterface
{
    use InteractsWithApplications;

    /**
     * Application store.
     *
     * @var array<string, array<string, array<string, \Laravel\Reverb\Channels\Channel>>>
     */
    protected $applications = [];

    /**
     * The appliation instance.
     *
     * @var \Laravel\Reverb\Application
     */
    protected $application;

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
    public function all(): Collection
    {
        return collect($this->channels());
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
    public function channel(string $channel): array
    {
        return $this->channels($channel);
    }

    /**
     * Get the channels.
     */
    public function channels(string $channel = null): array
    {
        if (! isset($this->applications[$this->application->id()])) {
            $this->applications[$this->application->id()] = [];
        }

        $channels = $this->applications[$this->application->id()];

        if ($channel) {
            return $channels[$channel] ?? ChannelBroker::create($channel);
        }

        return $channels ?: [];
    }

    /**
     * Flush the channel manager repository.
     */
    public function flush(): void
    {
        App::make(ApplicationProvider::class)
            ->all()
            ->each(function (Application $application) {
                $this->applications[$application->id()] = [];
            });
    }
}
