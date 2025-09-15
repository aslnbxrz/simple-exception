<?php

namespace Aslnbxrz\SimpleException\Tests;

use Aslnbxrz\SimpleException\SimpleExceptionServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SimpleExceptionServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use testing env and default template
        $app['config']->set('simple-exception.environment', 'testing');
        $app['config']->set('simple-exception.response.template', 'default');
        $app['config']->set('app.debug', true); // meta visible
        $app['config']->set('simple-exception.translations.locales', ['en']);
        $app['config']->set('simple-exception.translations.locale_fallback', 'en');
        $app['config']->set('simple-exception.translations.messages', [
            'patterns' => [
                'en' => ':readable error occurred.',
            ],
        ]);
    }
}