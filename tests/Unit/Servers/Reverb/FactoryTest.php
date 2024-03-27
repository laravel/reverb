<?php

use Laravel\Reverb\Servers\Reverb\Factory;
use React\Socket\SecureServer;
use React\Socket\TcpServer;

it('can create a server', function () {
    $server = Factory::make();

    $socket = (new ReflectionProperty($server, 'socket'))->getValue($server);
    $socketServer = (new ReflectionProperty($socket, 'server'))->getValue($socket);

    expect($socketServer)->toBeInstanceOf(TcpServer::class);

    $server->stop();
});

it('can create a server with the given host and port', function () {
    $server = Factory::make('127.0.0.1', '8001');

    $socket = (new ReflectionProperty($server, 'socket'))->getValue($server);
    $socketServer = (new ReflectionProperty($socket, 'server'))->getValue($socket);

    expect($socketServer)->toBeInstanceOf(TcpServer::class);
    expect($socketServer->getAddress())->toBe('tcp://127.0.0.1:8001');

    $server->stop();
});

it('can create a tls server using a user provided certificate', function () {
    $this->app->config->set('reverb.servers.reverb.options.tls.local_cert', '/path/to/cert.pem');
    $this->app->config->set('reverb.servers.reverb.options.tls.verify_peer', false);
    $server = Factory::make(options: $this->app->config->get('reverb.servers.reverb.options'));

    $socket = (new ReflectionProperty($server, 'socket'))->getValue($server);
    $socketServer = (new ReflectionProperty($socket, 'server'))->getValue($socket);
    $context = (new ReflectionProperty($socketServer, 'context'))->getValue($socketServer);

    expect($socketServer)->toBeInstanceOf(SecureServer::class);
    expect($context['local_cert'])->toBe('/path/to/cert.pem');
    expect($context['verify_peer'])->toBeFalse();

    $server->stop();
});

it('can create a server using tls on the given host and port', function () {
    $this->app->config->set('reverb.servers.reverb.options.tls.local_cert', '/path/to/cert.pem');
    $this->app->config->set('reverb.servers.reverb.options.tls.verify_peer', false);
    $server = Factory::make('127.0.0.1', '8002', options: $this->app->config->get('reverb.servers.reverb.options'));

    $socket = (new ReflectionProperty($server, 'socket'))->getValue($server);
    $socketServer = (new ReflectionProperty($socket, 'server'))->getValue($socket);

    expect($socketServer)->toBeInstanceOf(SecureServer::class);
    expect($socketServer->getAddress())->toBe('tls://127.0.0.1:8002');

    $server->stop();
});

it('can create a server without tls when context values are null', function () {
    $this->app->config->set('reverb.servers.reverb.options.tls.local_cert', null);
    $this->app->config->set('reverb.servers.reverb.options.tls.verify_peer', null);
    $server = Factory::make('127.0.0.1', '8002', options: $this->app->config->get('reverb.servers.reverb.options'));

    $socket = (new ReflectionProperty($server, 'socket'))->getValue($server);
    $socketServer = (new ReflectionProperty($socket, 'server'))->getValue($socket);

    expect($socketServer)->toBeInstanceOf(TcpServer::class);

    $server->stop();
});
