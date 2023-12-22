<?php

namespace Laravel\Reverb\Pusher\Channels;

use Laravel\Reverb\Pusher\Channels\Concerns\InteractsWithPrivateChannels;

class PrivateCacheChannel extends CacheChannel
{
    use InteractsWithPrivateChannels;
}
