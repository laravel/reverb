<?php

namespace Laravel\Reverb\Tests;

use Illuminate\Support\Collection;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ApplicationProvider;

class FakeApplicationProvider implements ApplicationProvider
{
    /**
     * The applications collection.
     *
     * @var \Illuminate\Support\Collection<\Laravel\Reverb\Application>
     */
    protected $apps;

    /**
     * Create a new fake provider instance.
     */
    public function __construct()
    {
        $this->apps = collect([
            new Application('id', 'key', 'secret', 60, 30, ['*'], 10_000, options: [
                'host' => 'localhost',
                'port' => 443,
                'scheme' => 'https',
                'useTLS' => true,
            ]),
        ]);
    }

    /**
     * Get all of the configured applications as Application instances.
     *
     * @return \Illuminate\Support\Collection<\Laravel\Reverb\Application>
     */
    public function all(): Collection
    {
        return $this->apps;
    }

    /**
     * Find an application instance by ID.
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public function findById(string $id): Application
    {
        return $this->apps->first();
    }

    /**
     * Find an application instance by key.
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public function findByKey(string $key): Application
    {
        return $this->apps->first();
    }
}
