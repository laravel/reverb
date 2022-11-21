<?php

namespace Laravel\Reverb\Exceptions;

class InvalidApplication extends PusherException
{
    protected $code = 4001;

    protected $message = 'Application does not exist';
}
