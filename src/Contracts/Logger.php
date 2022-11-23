<?php

namespace Laravel\Reverb\Contracts;

interface Logger
{
    /**
     * Log an infomational message.
     *
     * @param  string  $title
     * @param  string|null  $message
     * @return void
     */
    public function info(string $title, ?string $message = null): void;

    /**
     * Log an error message.
     *
     * @param  string  $message
     * @return void
     */
    public function error(string $message): void;

    /**
     * Append a new line to the log.
     *
     * @param  int  $lines
     * @return void
     */
    public function line(?int $lines = 1): void;

    /**
     * Log a message sent to the server.
     *
     * @param  string  $message
     * @return void
     */
    public function message(string $message): void;
}
