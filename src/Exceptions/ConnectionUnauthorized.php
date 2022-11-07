<?php

namespace Reverb\Exceptions;

class ConnectionUnauthorized extends PusherException
{
    protected $code = 4009;

    protected $message = 'Connection is unauthorized';
}
