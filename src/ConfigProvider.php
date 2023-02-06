<?php

namespace Laravel\Reverb;

use Illuminate\Support\Collection;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Exceptions\InvalidApplication;

class ConfigProvider implements ApplicationProvider
{
    public function __construct(protected Collection $applications)
    {
    }

    /**
     * Return all of the configured applications as Application instances.
     *
     * @return \Illuminate\Support\Collection|\Laravel\Reverb\Application[]
     */
    public function all(): Collection
    {
        return $this->applications->map(function ($app) {
            return $this->findById($app['id']);
        });
    }

    /**
     * Find an application instance by ID.
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public function findByKey(string $key): Application
    {
        return $this->find('key', $key);
    }

    /**
     * Find an application instance by key.
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public function findById(string $id): Application
    {
        return $this->find('id', $id);
    }

    /**
     * Find an application instance.
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public function find(string $key, $value): Application
    {
        $app = $this->applications->firstWhere($key, $value);

        if (! $app) {
            throw new InvalidApplication;
        }

        return new Application(
            $app['id'],
            $app['key'],
            $app['secret'],
            $app['ping_interval'],
            $app['allowed_origins'],
        );
    }
}
