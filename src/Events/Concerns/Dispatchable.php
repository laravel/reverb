<?php

namespace Laravel\Reverb\Events\Concerns;

use Laravel\Reverb\Loggers\Log;

trait Dispatchable
{
    /**
     * Dispatch the event with the given arguments.
     *
     * @return mixed
     */
    public static function dispatch()
    {
        try {
            return event(new static(...func_get_args()));
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }
}
