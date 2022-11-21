<?php

namespace Laravel\Reverb;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Exceptions\InvalidApplication;

class Application
{
    /**
     * Collection of app configurations.
     *
     * @var \Illuminate\Support\Collection
     */
    protected Collection $applications;

    /**
     * The application ID.
     *
     * @var string
     */
    protected string $id;

    /**
     * The application Ikey.
     *
     * @var string
     */
    protected string $key;

    /**
     * The application secret.
     *
     * @var string
     */
    protected string $secret;

    /**
     * The capacity of connections the application can handle.
     *
     * @var string
     */
    protected ?int $capacity;

    /**
     * The allowed origins from which the application can be connected.
     *
     * @var array
     */
    protected array $allowedOrigins;

    public function __construct()
    {
        $this->applications = collect(Config::get('reverb.apps'));
    }

    /**
     * Get an application instance by key.
     *
     * @param  string  $key
     * @return \Laravel\Reverb\Application
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public static function find(string $key): Application
    {
        $application = new static;

        $app = $application->applications->firstWhere('key', $key);

        if (! $app) {
            throw new InvalidApplication;
        }

        $application->id = $app['id'];
        $application->key = $app['key'];
        $application->secret = $app['secret'];
        $application->capacity = $app['capacity'];
        $application->allowedOrigins = $app['allowed_origins'];

        return $application;
    }

    /**
     * Get the application ID.
     *
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get the application key.
     *
     * @return string
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Get the application secret.
     *
     * @return string
     */
    public function secret(): string
    {
        return $this->secret;
    }

    /**
     * Get the application capacity.
     *
     * @return int
     */
    public function capacity(): string
    {
        return $this->capacity;
    }

    /**
     * Get the allowed origins.
     *
     * @return array
     */
    public function allowedOrigins(): array
    {
        return $this->allowedOrigins;
    }
}
