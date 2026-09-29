<?php

namespace Laravel\Reverb\Tests\Feature\Diagnostics;

use Laravel\Doctor\DoctorServiceProvider;
use Laravel\Reverb\Tests\TestCase;

abstract class DiagnosticsTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(DoctorServiceProvider::class)) {
            $this->markTestSkipped('laravel/doctor is not installed.');
        }

        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            DoctorServiceProvider::class,
        ];
    }
}
