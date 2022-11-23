<?php

namespace Laravel\Reverb\Contracts;

interface Logger
{
    public function info(string $title, string $message): void;

    public function error(string $string): void;

    public function line($lines = 1): void;

    public function message(string $message): void;
}
