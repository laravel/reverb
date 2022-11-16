<?php

namespace Laravel\Reverb\Servers\Ratchet;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Reverb\Servers\Ratchet\Console\Commands\StartServer;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                StartServer::class,
            ]);
        }
    }
}
