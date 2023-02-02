<?php

namespace Laravel\Reverb;

use Illuminate\Support\Facades\Facade;
use Laravel\Reverb\Contracts\Logger;

class Output extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @throws \RuntimeException
     */
    protected static function getFacadeAccessor(): string
    {
        return Logger::class;
    }
}
