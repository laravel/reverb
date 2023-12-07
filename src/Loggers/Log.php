<?php

namespace Laravel\Reverb\Loggers;

use Laravel\Reverb\Contracts\Logger;

class Log
{
    /**
     * The logger instance.
     *
     * @var \Laravel\Reverb\Contracts\Logger
     */
    protected static $logger;

    /**
     * Proxy method calls to the logger instance.
     *
     * @param  string  $name
     * @param  array  $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        if (! static::$logger) {
            static::$logger = app(Logger::class);
        }

        return static::$logger->$name(...$arguments);
    }
}
