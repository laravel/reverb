<?php

namespace Laravel\Reverb\Channels;

use Illuminate\Support\Str;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Exceptions\ConnectionUnauthorized;

class PrivateChannel extends Channel
{
    /**
     * Subscribe to the given channel.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @param  string  $auth
     * @param  string|null  $data
     * @return bool
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        $this->verify($connection, $auth, $data);

        parent::subscribe($connection, $auth, $data);
    }

    /**
     * Deteremine whether the given auth token is valid.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @param  string  $auth
     * @param  string|null  $data
     * @return bool
     */
    protected function verify(Connection $connection, string $auth, ?string $data = null): bool
    {
        $signature = "{$connection->id()}:{$this->name()}";

        if ($data) {
            $signature .= ":{$data}";
        }

        if (! hash_equals(
            hash_hmac(
                'sha256',
                $signature,
                $connection->app()->secret(),
            ),
            Str::after($auth, ':')
        )) {
            throw new ConnectionUnauthorized;
        }

        return true;
    }
}
