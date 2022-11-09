<?php

namespace Reverb\Servers\ApiGateway;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Reverb\Event;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        Route::post('/apps/{appId}/events', function (Request $request) {
            Event::dispatch($request->getContent());

            return new JsonResponse((object) []);
        });
    }
}
