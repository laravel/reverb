<?php

namespace Laravel\Reverb\Tests;

use Laravel\Reverb\ApplicationManagerServiceProvider;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\NullLogger;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelManager;
use Laravel\Reverb\ReverbServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

class TestCase extends TestbenchTestCase
{
    /**
     * Setup the test environment.
     */
    protected function defineEnvironment($app): void
    {
        $app->instance(Logger::class, new NullLogger);

        $app->singleton(
            ChannelManager::class,
            fn () => new ArrayChannelManager
        );

        $app->bind(
            ChannelConnectionManager::class,
            fn () => new ArrayChannelConnectionManager
        );
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            ReverbServiceProvider::class,
            ApplicationManagerServiceProvider::class,
        ];
    }
}
