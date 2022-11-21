<?php

namespace Laravel\Reverb\Channels;

use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Exceptions\ConnectionUnauthorized;

class PrivateChannel extends Channel
{
    /**
     * Subscribe to the given channel.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  string  $auth
     * @param  string  $data
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
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  string  $auth
     * @return bool
     */
    protected function verify(Connection $connection, string $auth, string $data = null): bool
    {
        $signature = "{$connection->id()}:{$this->name()}";

        if ($data) {
            $signature .= ":{$data}";
        }

        if (! hash_equals(
            hash_hmac(
                'sha256',
                $signature,
                $connection->application()->secret(),
            ),
            Str::after($auth, ':')
        )) {
            throw new ConnectionUnauthorized;
        }

        return true;
    }
}
