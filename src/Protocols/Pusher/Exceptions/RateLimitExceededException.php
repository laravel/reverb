<?php

namespace Laravel\Reverb\Protocols\Pusher\Exceptions;

use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

/**
 * Exception thrown when a client exceeds the rate limit for WebSocket connections.
 */
class RateLimitExceededException extends PusherException
{
    /**
     * The error code associated with the exception.
     *
     * @var int
     */
    protected $code = HttpFoundationResponse::HTTP_TOO_MANY_REQUESTS;

    /**
     * The error message associated with the exception.
     *
     * @var string
     */
    protected $message = 'Too Many Requests';
}