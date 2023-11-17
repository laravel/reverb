<?php

use Laravel\Reverb\Tests\RatchetTestCase;

use function React\Async\await;

uses(RatchetTestCase::class);

it('can return all channel information', function () {
    $this->subscribe('test-channel-one');
    $this->subscribe('test-channel-two');

    $response = await($this->getWithSignature('channels?info=user_count'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"channels":{"test-channel-one":{"user_count":1},"test-channel-two":{"user_count":1}}}', $response->getBody()->getContents());
});
