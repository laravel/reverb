<?php

namespace Laravel\Reverb\Channels;

use Laravel\Reverb\Channels\Concerns\InteractsWithPresenceChannels;

class PresenceCacheChannel extends CacheChannel
{
    use InteractsWithPresenceChannels;
}
