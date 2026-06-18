<?php

namespace Laravel\Reverb;

class Reverb
{
    /**
     * Helper method to register the Reverb development server Artisan command when running in a local environment.
     * Should be called from the `boot` method of an application service provider.
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
