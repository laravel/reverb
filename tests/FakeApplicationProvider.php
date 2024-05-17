<?php

namespace Laravel\Reverb\Tests;

use Illuminate\Support\Collection;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ApplicationProvider;

class FakeApplicationProvider implements ApplicationProvider  {

    protected $apps;

    public function __construct()
    {
        $this->apps = collect([
            new Application('id', 'key', 'secret', 60, ['*'], 10_000, [
                'host' => 'localhost',
                'port' => 443,
                'scheme' => 'https',
                'useTLS' => true,
            ])
        ]);
    }

    public function all(): Collection
    {
        return $this->apps;
    }

    public function findById(string $id): Application
    {
        return $this->apps->first();
    }

    public function findByKey(string $key): Application
    {
        return $this->apps->first();
    }
}