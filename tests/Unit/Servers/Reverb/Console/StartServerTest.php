<?php

use Symfony\Component\Process\Process;

it('exits when an exception is thrown before the server starts', function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    $process = new Process(
        [PHP_BINARY, 'vendor/bin/testbench', 'reverb:start', '--host=127.0.0.1', "--port={$port}"],
        dirname(__DIR__, 5),
        [
            'CACHE_STORE' => 'database',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ],
        timeout: 15,
    );

    $process->run();

    expect($process->getExitCode())->toBe(1);
    expect($process->getOutput().$process->getErrorOutput())->toContain('no such table: cache');
});
