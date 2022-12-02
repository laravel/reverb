<?php

namespace Laravel\Reverb\Loggers;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Laravel\Reverb\Console\Components\Message;
use Laravel\Reverb\Contracts\Logger;

class CliLogger implements Logger
{
    protected $components;

    public function __construct(protected OutputStyle $output)
    {
        $this->components = new Factory($output);
    }

    /**
     * Log an infomational message.
     *
     * @param  string  $title
     * @param  string|null  $message
     * @return void
     */
    public function info(string $title, ?string $message = null): void
    {
        $this->components->twoColumnDetail($title, $message);
    }

    /**
     * Log an error message.
     *
     * @param  string  $message
     * @return void
     */
    public function error(string $string): void
    {
        $this->output->error($string);
    }

    /**
     * Append a new line to the log.
     *
     * @param  int  $lines
     * @return void
     */
    public function line(?int $lines = 1): void
    {
        $this->output->newLine($lines);
    }

    /**
     * Log a message sent to the server.
     *
     * @param  string  $message
     * @return void
     */
    public function message(string $message): void
    {
        $message = json_decode($message, true);

        if (isset($message['data']) && is_string($message['data'])) {
            $message['data'] = json_decode($message['data'], true);
        }

        if (isset($message['data']['channel_data'])) {
            $message['data']['channel_data'] = json_decode($message['data']['channel_data'], true);
        }

        $message = json_encode($message, JSON_PRETTY_PRINT);

        with(new Message($this->output))->render(
            $message
        );
    }
}
