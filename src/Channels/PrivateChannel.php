<?php

namespace Reverb\Channels;

use Illuminate\Support\Str;
use Reverb\Connection;
use Reverb\Exceptions\ConnectionUnauthorized;

class PrivateChannel extends Channel
{
    /**
     * Subscribe to the given channel.
     *
     * @param  Connection  $connection
     * @param  string  $auth
     * @return bool
     */
    public function subscribe(Connection $connection, string $auth): void
    {
        $this->verify($connection, $auth);

        parent::subscribe($connection, $auth);
    }

    /**
     * Deteremine whether the given auth token is valid.
     *
     * @param  Connection  $connection
     * @param  string  $auth
     * @return bool
     */
    protected function verify(Connection $connection, string $auth): bool
    {
        $signature = "{$connection->id()}:{$this->name()}";

        if (! hash_equals(
            hash_hmac('sha256', $signature, 'abc'),
            Str::after($auth, ':')
        )) {
            throw new ConnectionUnauthorized;
        }

        return true;
    }
}
