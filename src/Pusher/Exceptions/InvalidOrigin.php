<?php

namespace Laravel\Reverb\Pusher\Exceptions;

use Laravel\Reverb\Pusher\Exceptions\PusherException;

class InvalidOrigin extends PusherException
{
    /**
     * The error code associated with the exception.
     *
     * @var int
     */
    protected $code = 4009;

    /**
     * The error message associated with the exception.
     *
     * @var string
     */
    protected $message = 'Origin not allowed';
}
