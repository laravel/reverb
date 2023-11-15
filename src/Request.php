<?php

namespace Laravel\Reverb;

use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;

class Request
{
    public static function from(string $message): RequestInterface
    {
        return Message::parseRequest($message);
    }
}