<?php

namespace Laravel\Reverb\Http\Controllers;

use Illuminate\Support\Facades\App;
use Laravel\Reverb\Contracts\ApplicationProvider;
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
        parse_str($request->getUri()->getQuery(), $queryString);
        $app = App::make(ApplicationProvider::class)->findById($queryString['appId']);

        tap($conn)->send(new JsonResponse((object) [
            'connections' => App::make(ConnectionManager::class)->for($app)->all()->count(),
            'channels' => App::make(ChannelManager::class)->for($app)->all()->map(function ($channel) {
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
