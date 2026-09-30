<?php

namespace Laravel\Reverb\Servers\Reverb\Http;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class HandshakeResponseFactory implements ResponseFactoryInterface
{
    /**
     * Create a new response for the WebSocket handshake.
     *
     * The handshake negotiator sets the "Sec-WebSocket-Version" header as an integer, which "guzzlehttp/psr7" 3.x no longer casts to a string.
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new class($code, [], null, '1.1', $reasonPhrase) extends Response
        {
            public function withHeader($header, $value): MessageInterface
            {
                return parent::withHeader($header, is_int($value) ? (string) $value : $value);
            }
        };
    }
}
