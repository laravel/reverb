<?php

namespace Laravel\Reverb\Pusher\Channels;

use Laravel\Reverb\Pusher\Channels\Concerns\InteractsWithPrivateChannels;

class PrivateChannel extends Channel
{
    use InteractsWithPrivateChannels;
}
