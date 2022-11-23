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

    public function info(string $title, string $message = null): void
    {
        $this->components->twoColumnDetail($title, $message);
    }

    public function error(string $string): void
    {
        $this->output->error($string);
    }

    public function line($lines = 1): void
    {
        $this->output->newLine($lines);
    }

    public function message(string $message): void
    {
        $message = json_decode($message, true);

        if (isset($message['data']['channel_data'])) {
            $message['data']['channel_data'] = json_decode($message['data']['channel_data'], true);
        }

        $message = json_encode($message, JSON_PRETTY_PRINT);

        with(new Message($this->output))->render(
            $message
        );
    }
}
