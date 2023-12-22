<?php

namespace Laravel\Reverb\Exceptions;

use Laravel\Reverb\Pusher\Exceptions\PusherException;

class InvalidApplication extends PusherException
{
    /**
     * The error code associated with the exception.
     *
     * @var int
     */
    protected $code = 4001;

    /**
     * The error message associated with the exception.
     *
     * @var string
     */
    protected $message = 'Application does not exist';
}
