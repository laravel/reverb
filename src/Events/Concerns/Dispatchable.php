<?php

namespace Laravel\Reverb\Events\Concerns;

use Laravel\Reverb\Loggers\Log;

trait Dispatchable
{
    /**
     * Proxy method calls to the Dispatchable.
     *
     * @param  string  $method
     * @param  array  $arguments
     * @return mixed
     */
    public static function __callStatic($method, $arguments)
    {
        $dispatchable = new class
        {
            use \Illuminate\Foundation\Events\Dispatchable;
        };

        try {
            $dispatchable::$method(...$arguments);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }
}
