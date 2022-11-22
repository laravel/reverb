<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Reverb\Application;
use Laravel\Reverb\Event;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        Route::post('/apps/{appId}/events', function (Request $request, $appId) {
            Event::dispatch(Application::findById($appId), [
                'event' => $request->name,
                'channel' => $request->channel,
                'data' => $request->data,
            ]);

            return new JsonResponse((object) []);
        });
    }
}
