<?php

namespace Laravel\Reverb;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Exceptions\InvalidApplication;

class Application
{
    protected Collection $applications;

    protected string $id;

    protected string $key;

    protected string $secret;

    protected ?int $capacity;

    protected array $allowedOrigins;

    public function __construct()
    {
        $this->applications = collect(Config::get('reverb.apps'));
    }

    public static function find(string $key)
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

    public function id(): string
    {
        return $this->id;
    }

    public function key()
    {
        return $this->key;
    }

    public function secret()
    {
        return $this->secret;
    }

    public function capacity()
    {
        return $this->capacity;
    }

    public function allowedOrigins()
    {
        return $this->allowedOrigins;
    }
}
