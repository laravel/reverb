<?php

namespace Laravel\Reverb\Concerns;

use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use Laravel\Reverb\Http\Connection;

trait ClosesConnections
{
    /**
     * Close the connection.
     */
    protected function close(Connection $connection, int $statusCode = 400, array $headers = []): void
    {
        $response = new Response($statusCode, $headers);

        $connection->send(Message::toString($response));
        $connection->close();
    }
}
