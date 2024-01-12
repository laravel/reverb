<?php

namespace Laravel\Reverb\Contracts;

use Illuminate\Support\Collection;
use Laravel\Reverb\Application;

interface ApplicationProvider
{
    /**
     * Get all of the configured applications as Application instances.
     *
     * @return \Illuminate\Support\Collection<\Laravel\Reverb\Application>
     */
    public function all(): Collection;

    /**
     * Find an application instance by ID.
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public function findById(string $id): Application;

    /**
     * Find an application instance by key.
     *
     * @throws \Laravel\Reverb\Exceptions\InvalidApplication
     */
    public function findByKey(string $key): Application;
}
