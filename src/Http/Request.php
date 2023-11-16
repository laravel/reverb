<?php

namespace Laravel\Reverb\Http;

use GuzzleHttp\Psr7\Message;
use OverflowException;
use Psr\Http\Message\RequestInterface;

class Request
{
    /**
     * End of message delimiter.
     *
     * @var string
     */
    const EOM = "\r\n\r\n";

    /**
     * The maximum number of allowed bytes for the request.
     *
     * @var int
     */
    const MAX_SIZE = 4096;

    public static function from(string $message, Connection $connection): ?RequestInterface
    {
        $connection->appendToBuffer($message);

        if ($connection->bufferLength() > static::MAX_SIZE) {
            throw new OverflowException('Maximum HTTP buffer size of '.static::MAX_SIZE.'exceeded.');
        }

        if (static::isEndOfMessage($buffer = $connection->buffer())) {
            $connection->clearBuffer();

            return Message::parseRequest($buffer);
        }

        return null;
    }

    /**
     * Determine if the message has been buffered as per the HTTP specification
     */
    protected static function isEndOfMessage(string $message): bool
    {
        return (bool) strpos($message, static::EOM);
    }
}
