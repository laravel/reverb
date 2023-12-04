<?php

namespace Laravel\Reverb\Pusher\Concerns;

use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Channels\Concerns\InteractsWithPresenceChannels;
use Laravel\Reverb\Contracts\ChannelManager;

trait InteractsWithChannelInformation
{
    /**
     * Get the info for the given channels.
     */
    protected function infoForChannels(array $channels, string $info): array
    {
        return collect($channels)->mapWithKeys(function ($channel) use ($info) {
            $name = $channel instanceof Channel ? $channel->name() : $channel;

            return [$name => $this->info($name, $info)];
        })->all();
    }

    /**
     * Get the info for the given channels.
     *
     * @param  array<int, string>  $channels
     * @return array<string, array<string, int>>
     */
    protected function info(string $channel, string $info): array
    {
        $info = explode(',', $info);

        if (! $channel = app(ChannelManager::class)->find($channel)) {
            return [];
        }

        $count = count($channel->connections());

        $info = [
            'user_count' => in_array('user_count', $info) && $this->isPresenceChannel($channel) ? $count : null,
            'subscription_count' => in_array('subscription_count', $info) && ! $this->isPresenceChannel($channel) ? $count : null,
        ];

        return array_filter($info, fn ($item) => $item !== null);
    }

    /**
     * Determine if the channel is a presence channel.
     */
    protected function isPresenceChannel(Channel $channel): bool
    {
        return in_array(InteractsWithPresenceChannels::class, class_uses($channel));
    }
}
