<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Illuminate\Support\Arr;

class Request
{
    /**
     * Create a new request instance.
     */
    public function __construct(
        public array $serverVariables,
        public string $body,
        public array $headers,
        public string $connectionId,
        public string $event
    ) {
    }

    /**
     * Create a new request from the given Lambda event.
     */
    public static function fromLambdaEvent(array $event, array $serverVariables = [], $handler = null): Request
    {
        if ($event['requestContext']['eventType'] === 'MESSAGE') {
            return new static(
                $serverVariables,
                static::getRequestBody($event),
                [],
                $event['requestContext']['connectionId'],
                $event['requestContext']['eventType'],
            );
        }

        $queryString = static::getQueryString($event);

        $headers = static::getHeaders($event);

        $requestBody = static::getRequestBody($event);

        $serverVariables = array_merge($serverVariables, [
            'QUERY_STRING' => $queryString,
            'REMOTE_ADDR' => '127.0.0.1',
            'REMOTE_PORT' => $headers['x-forwarded-port'] ?? 80,
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_ADDR' => '127.0.0.1',
            'SERVER_NAME' => $headers['host'] ?? 'localhost',
            'SERVER_PORT' => $headers['x-forwarded-port'] ?? 80,
            'SERVER_SOFTWARE' => 'vapor',
        ]);

        [$headers, $serverVariables] = static::ensureContentTypeIsSet(
            $event, $headers, $serverVariables
        );

        $headers = static::ensureSourceIpAddressIsSet(
            $event, $headers
        );

        foreach ($headers as $header => $value) {
            $serverVariables['HTTP_'.strtoupper(str_replace('-', '_', $header))] = $value;
        }

        return new static(
            $serverVariables,
            $requestBody,
            $headers,
            $event['requestContext']['connectionId'],
            $event['requestContext']['eventType'],
        );
    }

    /**
     * Get the URI and query string for the given event.
     */
    protected static function getUriAndQueryString(array $event): array
    {
        $uri = $event['requestContext']['http']['path'] ?? $event['path'] ?? '/';

        $queryString = self::getQueryString($event);

        parse_str($queryString, $queryParameters);

        return [
            empty($queryString) ? $uri : $uri.'?'.$queryString,
            http_build_query($queryParameters),
        ];
    }

    /**
     * Get the query string from the event.
     */
    protected static function getQueryString(array $event): string
    {
        if (isset($event['version']) && $event['version'] === '2.0') {
            return http_build_query(
                collect($event['queryStringParameters'] ?? [])
                    ->mapWithKeys(function ($value, $key) {
                        $values = explode(',', $value);

                        return count($values) === 1
                            ? [$key => $values[0]]
                            : [(substr($key, -2) == '[]' ? substr($key, 0, -2) : $key) => $values];
                    })->all()
            );
        }

        if (! isset($event['multiValueQueryStringParameters'])) {
            return http_build_query(
                $event['queryStringParameters'] ?? []
            );
        }

        return http_build_query(
            collect($event['multiValueQueryStringParameters'] ?? [])
                ->mapWithKeys(function ($values, $key) use ($event) {
                    $key = ! isset($event['requestContext']['elb']) ? $key : urldecode($key);

                    return count($values) === 1
                        ? [$key => $values[0]]
                        : [(substr($key, -2) == '[]' ? substr($key, 0, -2) : $key) => $values];
                })->map(function ($values) use ($event) {
                    if (! isset($event['requestContext']['elb'])) {
                        return $values;
                    }

                    return ! is_array($values) ? urldecode($values) : array_map(function ($value) {
                        return urldecode($value);
                    }, $values);
                })->all()
        );
    }

    /**
     * Get the request headers from the event.
     */
    protected static function getHeaders(array $event): array
    {
        if (! isset($event['multiValueHeaders'])) {
            return array_change_key_case(
                $event['headers'] ?? [], CASE_LOWER
            );
        }

        return array_change_key_case(
            collect($event['multiValueHeaders'] ?? [])
                ->mapWithKeys(function ($headers, $name) {
                    return [$name => Arr::last($headers)];
                })->all(), CASE_LOWER
        );
    }

    /**
     * Get the request body from the event.
     */
    protected static function getRequestBody(array $event): string
    {
        $body = $event['body'] ?? '';

        return isset($event['isBase64Encoded']) && $event['isBase64Encoded']
            ? base64_decode($body)
            : $body;
    }

    /**
     * Ensure the request headers / server variables contain a content type.
     */
    protected static function ensureContentTypeIsSet(array $event, array $headers, array $serverVariables): array
    {
        if ((! isset($headers['content-type']) && isset($event['httpMethod']) && (strtoupper($event['httpMethod']) === 'POST')) ||
            (! isset($headers['content-type']) && isset($event['requestContext']['http']['method']) && (strtoupper($event['requestContext']['http']['method']) === 'POST'))) {
            $headers['content-type'] = 'application/x-www-form-urlencoded';
        }

        if (isset($headers['content-type'])) {
            $serverVariables['CONTENT_TYPE'] = $headers['content-type'];
        }

        return [$headers, $serverVariables];
    }

    /**
     * Ensure the request headers / server variables contain a content length.
     */
    protected static function ensureContentLengthIsSet(array $event, array $headers, array $serverVariables, $requestBody): array
    {
        if ((! isset($headers['content-length']) && isset($event['httpMethod']) && ! in_array(strtoupper($event['httpMethod']), ['TRACE'])) ||
            (! isset($headers['content-length']) && isset($event['requestContext']['http']['method']) && ! in_array(strtoupper($event['requestContext']['http']['method']), ['TRACE']))) {
            $headers['content-length'] = strlen($requestBody);
        }

        if (isset($headers['content-length'])) {
            $serverVariables['CONTENT_LENGTH'] = $headers['content-length'];
        }

        return [$headers, $serverVariables];
    }

    /**
     * Ensure the request headers contain a source IP address.
     */
    protected static function ensureSourceIpAddressIsSet(array $event, array $headers): array
    {
        if (isset($event['requestContext']['identity']['sourceIp'])) {
            $headers['x-vapor-source-ip'] = $event['requestContext']['identity']['sourceIp'];
        }

        if (isset($event['requestContext']['http']['sourceIp'])) {
            $headers['x-vapor-source-ip'] = $event['requestContext']['http']['sourceIp'];
        }

        return $headers;
    }

    /**
     * Get the event from the Lambda event.
     */
    public function event(): string
    {
        return $this->event;
    }

    /**
     * Get the message from the Lambda event.
     */
    public function message(): string
    {
        return $this->body;
    }

    /**
     * Determine whether the request is a connection.
     */
    public function isConnection(): bool
    {
        return $this->event === 'CONNECT';
    }

    /**
     * Determine whether the request is a disconnection.
     */
    public function isDisconnection(): bool
    {
        return $this->event === 'DISCONNECT';
    }

    /**
     * Determine whether the request is a message.
     */
    public function isMessage(): bool
    {
        return $this->event === 'MESSAGE';
    }

    /**
     * Get the connection ID for the request.
     */
    public function connectionId(): string
    {
        return $this->connectionId;
    }
}
