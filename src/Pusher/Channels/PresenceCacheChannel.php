<?php

namespace Laravel\Reverb\Pusher\Channels;

use Laravel\Reverb\Pusher\Channels\Concerns\InteractsWithPresenceChannels;

class PresenceCacheChannel extends CacheChannel
{
    use InteractsWithPresenceChannels;
}
