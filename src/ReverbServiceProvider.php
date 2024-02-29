<?php

namespace Laravel\Reverb;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\NullLogger;
use Laravel\Reverb\Pulse\Reverb;
use Laravel\Reverb\Pulse\Livewire;
use Livewire\LivewireManager;

class ReverbServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/reverb.php', 'reverb'
        );

        $this->app->instance(Logger::class, new NullLogger);

        $this->app->singleton(ServerProviderManager::class);

        $this->app->make(ServerProviderManager::class)->register();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/reverb.php' => config_path('reverb.php'),
            ], ['reverb', 'reverb-config']);
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'reverb');

        if ($this->app->bound(\Laravel\Pulse\Pulse::class)) {
            $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade) {
                $blade->anonymousComponentPath(__DIR__.'/../resources/views', 'reverb');
            });

            $this->callAfterResolving('livewire', function (LivewireManager $livewire, Application $app) {
                $livewire->component('reverb.messages', Livewire\Messages::class);
                $livewire->component('reverb.connections', Livewire\Connections::class);
            });
        }

        $this->app->make(ServerProviderManager::class)->boot();
    }
}
