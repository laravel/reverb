<?php

namespace Reverb\Exceptions;

use Exception;

abstract class PusherException extends Exception
{
    protected $code;

    protected $message;

    public function payload()
    {
        return [
            'event' => 'pusher:error',
            'data' => [
                'code' => $this->code,
                'message' => $this->message,
            ],
        ];
    }
}
