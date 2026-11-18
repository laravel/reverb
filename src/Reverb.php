<?php

namespace Laravel\Reverb;

use Closure;
use Illuminate\Foundation\DevCommands;

class Reverb
{
    /**
     * The callback used to verify origins that are not in an application's allowed origins.
     *
     * @var (Closure(string, Application): bool)|null
     */
    protected static ?Closure $verifyOriginCallback = null;

    /**
     * Register the Reverb dev commands.
     */
    public static function registerDevCommands(): void
    {
        if (class_exists(DevCommands::class)) {
            DevCommands::artisan('reverb:start', 'reverb');
        }
    }

    /**
     * Register a callback to verify origins that are not in an application's allowed origins.
     *
     * @param  (Closure(string, Application): bool)|null  $callback
     */
    public static function verifyOriginUsing(?Closure $callback): void
    {
        static::$verifyOriginCallback = $callback;
    }

    /**
     * Determine if the given origin is allowed by the origin verification callback.
     */
    public static function originIsAllowedByCallback(string $origin, Application $application): bool
    {
        if (is_null(static::$verifyOriginCallback)) {
            return false;
        }

        return call_user_func(static::$verifyOriginCallback, $origin, $application) === true;
    }
}
