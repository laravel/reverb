<?php

use Laravel\Reverb\Application;
use Laravel\Reverb\ApplicationManager;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Tests\FakeApplicationProvider;

it('limits channels per connection when the configuration omits the setting', function () {
    config(['reverb.apps.apps' => [[
        'app_id' => 'id',
        'key' => 'key',
        'secret' => 'secret',
        'ping_interval' => 60,
        'allowed_origins' => ['*'],
        'max_message_size' => 10_000,
    ]]]);

    expect($this->app->make(ApplicationProvider::class)->findByKey('key')->maxChannelsPerConnection())->toBe(1000);
});

it('retrieves applications from custom provider', function () {
    $this->app->make(ApplicationManager::class)->extend('fake', fn () => new FakeApplicationProvider);

    config([
        'reverb.apps.provider' => 'fake',
        'reverb.apps.apps' => [],
    ]);

    $applicationsProvider = $this->app->make(ApplicationProvider::class);
    $application = $applicationsProvider->all()->first();

    expect($applicationsProvider->all())->toHaveLength(1)
        ->and($application)->toBeInstanceOf(Application::class)
        ->and($application->toArray())->toMatchArray([
            'app_id' => 'id',
            'key' => 'key',
            'secret' => 'secret',
            'ping_interval' => 60,
            'allowed_origins' => ['*'],
            'max_message_size' => 10_000,
            'options' => [
                'host' => 'localhost',
                'port' => 443,
                'scheme' => 'https',
                'useTLS' => true,
            ],
        ]);
});
