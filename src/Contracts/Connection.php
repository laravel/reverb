<?php

namespace Laravel\Reverb\Contracts;

use Laravel\Reverb\Application;

interface Connection
{
    /**
     * Get the raw socket connection identifier.
     *
     * @return string
     */
    public function identifier(): string;

    /**
     * Get the normalized socket ID.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Send a message to the connection.
     *
     * @param  string  $message
     * @return void
     */
    public function send(string $message): void;

    public function application(): Application;
}
