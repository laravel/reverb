<?php

it('can forward a client message', function () {
    expect(true)->toBeTrue();
})->skip();

it('does not forward a message to itself', function () {
    expect(true)->toBeTrue();
})->skip();

it('fails on unsupported message', function () {
    expect(true)->toBeTrue();
})->skip();
