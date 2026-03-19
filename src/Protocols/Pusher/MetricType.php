<?php

namespace Laravel\Reverb\Protocols\Pusher;

enum MetricType: string
{
    case CONNECTIONS = 'connections';
    case CHANNEL = 'channel';
    case CHANNELS = 'channels';
    case CHANNEL_USERS = 'channel_users';
    case PRESENCE_DATA = 'presence_data';
}
