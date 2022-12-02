<?php

use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Tests\Connection;
use Laravel\Reverb\Tests\SerializableConnection;

beforeEach(function () {
    $this->connection = new Connection;
    $this->connectionManager = $this->app->make(ConnectionManager::class)
        ->for($this->connection->app());
});

it('can resolve an existing connection', function () {
    $connection = new Connection('my-connection');
    $this->connectionManager->sync(
        collect()
            ->put($connection->identifier(), $connection)
    );

    $connection = $this->connectionManager->resolve(
        'my-connection',
        function () {
            throw new Exception('This should not be called.');
        }
    );

    expect($connection->identifier())
        ->toBe('my-connection');
})->not->throws(Exception::class);

it('resolve and store a new connection', function () {
    $this->connectionManager->sync(
        collect()
            ->put($this->connection->identifier(), $this->connection)
    );

    $connection = $this->connectionManager->resolve(
        'my-connection',
        function () {
            throw new Exception('Creating new connection.');
        }
    );

    expect($connection->identifier())
        ->toBe('my-connection');
})->throws(Exception::class, 'Creating new connection.');

it('disconnect a connection', function () {
    $this->connectionManager->sync(
        collect()
            ->put($this->connection->identifier(), $this->connection)
    );

    expect($this->connectionManager->all())
        ->toHaveCount(1);

    $this->connectionManager->disconnect($this->connection->identifier());

    expect($this->connectionManager->all())
        ->toHaveCount(0);
});

it('get all connections', function () {
    $this->connectionManager->sync(
        connections(10)
            ->mapWithKeys(fn ($connection) => [$connection->identifier() => $connection])
    );

    expect($this->connectionManager->all())
        ->toHaveCount(10);
});

it('hydrate a serialized connection', function () {
    $connection = serialize(new SerializableConnection('my-connection'));

    $this->connectionManager->sync(
        collect()
            ->put('my-connection', $connection)
    );

    $this->expect(
        $this->connectionManager->resolve('my-connection', fn () => null)
    )->toBeInstanceOf(SerializableConnection::class);
});

it('hydrate an unserialized connection', function () {
    $connection = new Connection('my-connection');

    $this->connectionManager->sync(
        collect()
            ->put('my-connection', $connection)
    );

    $this->expect(
        $this->connectionManager->resolve('my-connection', fn () => null)
    )->toBeInstanceOf(Connection::class);
});

it('dehydrate a serialized connection', function () {
    $this->connectionManager->resolve(
        'my-connection',
        fn () => new SerializableConnection('my-connection')
    );

    expect($this->connectionManager->all()->get('my-connection'))
        ->toBeString();
});

it('dehydrate an unserialized connection', function () {
    $this->connectionManager->resolve(
        'my-connection',
        fn () => new Connection('my-connection')
    );

    expect($this->connectionManager->all()->get('my-connection'))
        ->toBeInstanceOf(Connection::class);
});
