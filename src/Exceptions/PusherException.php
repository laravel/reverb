<?php

namespace Laravel\Reverb\Exceptions;

use Exception;

abstract class PusherException extends Exception
{
    protected $code;

    protected $message;

    public function payload()
    {
        return [
            'event' => 'pusher:error',
            'data' => json_encode([
                'code' => $this->code,
                'message' => $this->message,
            ]),
        ];
    }
}
