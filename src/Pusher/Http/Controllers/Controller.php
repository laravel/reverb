<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Illuminate\Support\Arr;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\ClosesConnections;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Exceptions\InvalidApplication;
use Laravel\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class Controller
{
    use ClosesConnections;

    protected ?Application $application = null;

    protected $body;

    public function __invoke(RequestInterface $request, Connection $connection, ...$args)
    {
        $this->body = $request->getBody()->getContents();

        try {
            $this->setApplication($args['appId'] ?? null);
            $this->verifySignature($request);
        } catch (HttpException $e) {
            return $this->close($connection, $e->getStatusCode(), $e->getMessage());
        }

        return $this->handle($request, $connection, ...$args);
    }

    abstract public function handle(RequestInterface $request, Connection $connection, ...$args);

    /**
     * Set the Reverb application instance.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function setApplication(?string $appId): Application
    {
        if ($this->application) {
            return $this->application;
        }

        if (! $appId) {
            throw new HttpException(400, 'Application ID not provided.');
        }

        try {
            return $this->application = app(ApplicationProvider::class)->findById($appId);
        } catch (InvalidApplication $e) {
            throw new HttpException(404, 'No matching application for ID ['.$appId.'] found.');
        }
    }

    /**
     * Verify the Pusher signature.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function verifySignature(RequestInterface $request): void
    {
        parse_str($request->getUri()->getQuery(), $queryParams);

        $params = Arr::except($queryParams, [
            'auth_signature', 'body_md5', 'appId', 'appKey', 'channelName',
        ]);

        if ($this->body !== '') {
            $params['body_md5'] = md5($this->body);
        }

        ksort($params);

        $signature = implode("\n", [
            $request->getMethod(),
            $request->getUri()->getPath(),
            $this->formatParams($params),
        ]);

        $signature = hash_hmac('sha256', $signature, $this->application->secret());

        if ($signature !== $queryParams['auth_signature']) {
            throw new HttpException(401, 'Authentication signature invalid.');
        }
    }

    /**
     * Format the given parameters into the correct format for signature verification.
     */
    public static function formatParams(array $params): string
    {
        if (! is_array($params)) {
            return $params;
        }

        return collect($params)->map(function ($value, $key) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }

            return "{$key}={$value}";
        })->implode('&');
    }
}
