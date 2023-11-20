<?php

use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection;
    $this->connectionManager = $this->app->make(ConnectionManager::class)
        ->for($this->connection->app());
});

it('can resolve an existing connection', function () {
    $connection = new Connection('my-connection');
    $this->connectionManager->save($connection);

    $connection = $this->connectionManager->resolve(
        'my-connection',
        function () {
            throw new Exception('This should not be called.');
        }
    );

    expect($connection->identifier())
        ->toBe('my-connection');
});

it('can resolve and store a new connection', function () {
    $this->connectionManager->save($this->connection);

    $connection = $this->connectionManager->resolve(
        'my-connection',
        function () {
            throw new Exception('Creating new connection.');
        }
    );

    expect($connection->identifier())
        ->toBe('my-connection');
})->throws(Exception::class, 'Creating new connection.');

it('can disconnect a connection', function () {
    $this->connectionManager->save($this->connection);

    expect($this->connectionManager->all())
        ->toHaveCount(1);

    $this->connectionManager->disconnect($this->connection->identifier());

    expect($this->connectionManager->all())
        ->toHaveCount(0);
});

it('can get all connections', function () {
    $connections = collect(connections(10));
    $connections->each(fn ($connection) => $this->connectionManager->save($connection));

    expect($this->connectionManager->all())
        ->toHaveCount(10);
});
