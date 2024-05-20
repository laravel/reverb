<?php

use Laravel\Reverb\Tests\ReverbTestCase;

use function React\Async\await;

uses(ReverbTestCase::class);

it('fails when server is not running', function () {
    $this->stopServer();
    await($this->requestWithoutAppId('up'));
})->throws(RuntimeException::class);

it('can respond to a health check request', function () {
    $response = await($this->requestWithoutAppId('up'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"health":"OK"}');
    expect($response->getHeader('Content-Length'))->toBe(['15']);
});
