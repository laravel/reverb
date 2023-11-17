<?php

namespace Laravel\Reverb\Pusher\Http\Controllers;

use Illuminate\Support\Arr;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\ClosesConnections;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Exceptions\InvalidApplication;
use Laravel\Reverb\Http\Connection;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class Controller
{
    use ClosesConnections;

    protected ?Application $application = null;

    protected $connections;

    protected $channels;

    protected $body;

    public function __invoke(RequestInterface $request, Connection $connection, ...$args)
    {
        $this->body = $request->getBody()->getContents();

        try {
            $this->setApplication($args['appId'] ?? null);
            $this->setConnections();
            $this->setChannels();
        } catch (HttpException $e) {
            return $this->close($connection, $e->getStatusCode(), $e->getMessage());
        }

        return $this->handle($request, $connection, ...$args);
    }

    /**
     * Handle the incoming request.
     */
    abstract public function handle(RequestInterface $request, Connection $connection, ...$args): Response;

    /**
     * Set the Reverb application instance.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function setApplication(?string $appId): Application
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
     * Set the Reverb connection manager instance.
     */
    protected function setConnections()
    {
        $this->connections = app(ConnectionManager::class)->for($this->application);
    }

    /**
     * Set the Reverb channel manager instance.
     */
    protected function setChannels()
    {
        $this->channels = app(ChannelManager::class)->for($this->application);
    }

    /**
     * Verify the Pusher signature.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function verifySignature(RequestInterface $request): void
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
            // throw new HttpException(401, 'Authentication signature invalid.');
        }
    }

    /**
     * Format the given parameters into the correct format for signature verification.
     */
    protected static function formatParams(array $params): string
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
