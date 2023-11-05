<?php

namespace Laravel\Reverb\Http\Controllers;

use Illuminate\Support\Facades\App;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class StatsController implements HttpServerInterface
{
    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null)
    {
        tap($conn)->send(new JsonResponse((object) [
            'connections' => App::make(ConnectionManager::class)->all()->count(),
            'channels' => App::make(ChannelManager::class)->all()->map(function ($channel) {
                return [
                    'name' => $channel->name(),
                    'connections' => App::make(ChannelManager::class)
                        ->connectionKeys($channel)
                        ->count(),
                ];
            }),
        ]))->close();
    }

    public function onMessage(ConnectionInterface $from, $message)
    {
        //
    }

    public function onClose(ConnectionInterface $connection)
    {
        //
    }

    public function onError(ConnectionInterface $connection, \Exception $e)
    {
        //
    }
}