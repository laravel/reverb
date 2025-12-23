<?php

namespace Laravel\Reverb\Concerns;

use Closure;
use Illuminate\Support\Str;
use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Events\ConnectionPruned;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Events\MessageSent;

trait InteractsWithEvents
{
    /**
     * Register a callback to be executed when a channel is created.
     *
     * @param  array|string|Closure  $channels
     * @param  Closure|string|null  $callback
     * @return void
     */
    public function onChannelCreated($channels, $callback = null): void
    {
        $this->registerChannelEvent(ChannelCreated::class, $channels, $callback);
    }

    /**
     * Register a callback to be executed when a channel is removed.
     *
     * @param  array|string|\Closure  $channels
     * @param  Closure|string|null  $callback
     * @return void
     */
    public function onChannelRemoved($channels, $callback = null): void
    {
        $this->registerChannelEvent(ChannelRemoved::class, $channels, $callback);
    }

    /**
     * Register a callback to be executed when a message is sent.
     *
     * @param  array|string|\Closure  $channels
     * @param  Closure|string|null  $callback
     * @return void
     */
    public function onMessageSent($channels, $callback = null): void
    {
        $this->registerChannelEvent(MessageSent::class, $channels, $callback);
    }

    /**
     * Register a callback to be executed when a message is received.
     *
     * @param  Closure|string  $callback
     * @return void
     */
    public function onMessageReceived($callback): void
    {
        $this->app['events']->listen(MessageReceived::class, $this->resolveCallback($callback));
    }

    /**
     * Register a callback to be executed when a connection is pruned.
     *
     * @param  Closure|string  $callback
     * @return void
     */
    public function onConnectionPruned($callback): void
    {
        $this->app['events']->listen(ConnectionPruned::class, $this->resolveCallback($callback));
    }

    /**
     * Register a generic channel event hook with filtering capabilities.
     *
     * @param  string  $eventClass
     * @param  array|string|Closure  $channels
     * @param  Closure|string|null  $callback
     * @return void
     */
    protected function registerChannelEvent(string $eventClass, $channels, $callback): void
    {
        if ($channels instanceof Closure || (is_string($channels) && class_exists($channels))) {
            $callback = $channels;
            $channels = '*';
        }

        $this->app['events']->listen($eventClass, function ($event) use ($channels, $callback) {
            $channel = data_get($event, 'channel');

            if ($channel && Str::is($channels, $channel->name())) {
                $this->resolveCallback($callback)($event);
            }
        });
    }

    /**
     * Resolve the callback to a callable.
     *
     * @param  Closure|string  $callback
     * @return callable
     */
    protected function resolveCallback($callback): callable
    {
        if (is_string($callback)) {
            return $this->app->make($callback);
        }

        return $callback;
    }
}
