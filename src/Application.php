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
     * The interval in minutes check connections for activity.
     *
     * @var int
     */
    protected int $pingInterval;

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
     * Return all of the configured applications as Application instances.
     *
     * @return \Illuminate\Support\Collection|\Laravel\Reverb\Application[]
     */
    public static function all(): Collection
    {
        $application = new static;

        return $application->applications()->map(function ($app) use ($application) {
            return $application->findById($app['id']);
        });
    }

    /**
     * Find an application instance by ID.
     *
     * @param  string  $key
     * @return \Laravel\Reverb\Application
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public static function findByKey(string $key): Application
    {
        return static::find('key', $key);
    }

    /**
     * Find an application instance by key.
     *
     * @param  string  $id
     * @return \Laravel\Reverb\Application
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public static function findById(string $id): Application
    {
        return static::find('id', $id);
    }

    /**
     * Find an application instance.
     *
     * @param  string  $key
     * @param  string  $value
     * @return \Laravel\Reverb\Application
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public static function find(string $key, $value): Application
    {
        $application = new static;

        $app = $application->applications->firstWhere($key, $value);

        if (! $app) {
            throw new InvalidApplication;
        }

        $application->id = $app['id'];
        $application->key = $app['key'];
        $application->secret = $app['secret'];
        $application->allowedOrigins = $app['allowed_origins'];
        $application->pingInterval = $app['ping_interval'];

        return $application;
    }

    /**
     * Get the configured applications.
     *
     * @return \Illuminate\Support\Collection
     */
    public function applications(): Collection
    {
        return $this->applications;
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
     * Get the allowed origins.
     *
     * @return array
     */
    public function allowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**
     * Get the interval in minutes to ping the client.
     *
     * @return int
     */
    public function pingInterval(): int
    {
        return $this->pingInterval;
    }
}
