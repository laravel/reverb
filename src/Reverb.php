<?php

namespace Laravel\Reverb;

class Reverb
{
    /**
     * Register the Reverb dev commands.
     *
     * @return void
     */
    public static function registerDevCommands(): void
    {
        if (class_exists(\Illuminate\Foundation\DevCommands::class)) {
            \Illuminate\Foundation\DevCommands::artisan('reverb:start', 'reverb');
        }
    }
}
