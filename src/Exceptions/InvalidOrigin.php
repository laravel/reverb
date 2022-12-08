<?php

namespace Laravel\Reverb\Exceptions;

class InvalidOrigin extends PusherException
{
    protected $code = 4009;

    protected $message = 'Origin not allowed';
}
