<?php

use Laravel\Reverb\Tests\RatchetTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(RatchetTestCase::class);

it('returns an error when presence channel not provided', function () {
    await($this->signedRequest('channels/test-channel/users'));
})->throws(ResponseException::class);

it('returns the user data', function () {
    // 
})->todo();
