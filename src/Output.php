<?php

namespace Laravel\Reverb;

use Illuminate\Support\Facades\Facade;
use Laravel\Reverb\Contracts\Logger;

class Output extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Logger::class;
    }
}
