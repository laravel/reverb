<?php

namespace Laravel\Reverb\Pusher\Channels;

use Laravel\Reverb\Pusher\Channels\Concerns\InteractsWithPresenceChannels;

class PresenceChannel extends PrivateChannel
{
    use InteractsWithPresenceChannels;
}
