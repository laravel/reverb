<?php

namespace Laravel\Reverb\Loggers;

use Laravel\Reverb\Contracts\Logger;

class NullLogger implements Logger
{
    /**
     * Log an infomational message.
     */
    public function info(string $title, ?string $message = null): void
    {
        //
    }

    /**
     * Log an error message.
     */
    public function error(string $string): void
    {
        //
    }

    /**
     * Append a new line to the log.
     */
    public function line(int $lines = 1): void
    {
        //
    }

    /**
     * Log a message sent to the server.
     */
    public function message(string $message): void
    {
        //
    }
}
