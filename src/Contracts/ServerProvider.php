<?php

namespace Laravel\Reverb\Contracts;

abstract class ServerProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Build the connection manager for the server.
     */
    abstract public function buildConnectionManager(): ConnectionManager;

    /**
     * Build the channel manager for the server.
     */
    abstract public function buildChannelManager(): ChannelManager;
}
