<?php

namespace Laravel\Reverb\Channels;

use Laravel\Reverb\Channels\Concerns\InteractsWithPrivateChannels;

class PrivateCacheChannel extends CacheChannel
{
    use InteractsWithPrivateChannels;
}
