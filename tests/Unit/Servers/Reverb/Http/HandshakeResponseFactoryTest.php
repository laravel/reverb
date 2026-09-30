<?php

use Laravel\Reverb\Servers\Reverb\Http\HandshakeResponseFactory;

it('creates a response with the given status', function () {
    $response = (new HandshakeResponseFactory)->createResponse(101, 'Switching Protocols');

    expect($response->getStatusCode())->toBe(101);
    expect($response->getReasonPhrase())->toBe('Switching Protocols');
});

it('casts integer header values to strings', function () {
    $response = (new HandshakeResponseFactory)
        ->createResponse()
        ->withHeader('Sec-WebSocket-Version', 13);

    expect($response->getHeader('Sec-WebSocket-Version'))->toBe(['13']);
});

it('keeps the header casting on derived responses', function () {
    $response = (new HandshakeResponseFactory)
        ->createResponse()
        ->withStatus(426)
        ->withHeader('Upgrade', 'websocket')
        ->withHeader('Sec-WebSocket-Version', 13);

    expect($response->getStatusCode())->toBe(426);
    expect($response->getHeader('Sec-WebSocket-Version'))->toBe(['13']);
});
