<?php

namespace Aslnbxrz\SimpleException;

use Aslnbxrz\SimpleException\Console\Commands\MakeErrorRespCodeCommand;
use Aslnbxrz\SimpleException\Console\Commands\SyncTranslationsCommand;
use Aslnbxrz\SimpleException\Contracts\TranslatorDriver;
use Aslnbxrz\SimpleException\Enums\TranslationDriver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class SimpleExceptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Load helpers
        $helpers = __DIR__ . '/Support/helpers.php';
        if (is_file($helpers)) {
            require_once $helpers;
        }

        // Merge config
        $configPath = __DIR__ . '/../config/simple-exception.php';
        $this->mergeConfigFrom($configPath, 'simple-exception');

        // translator driver binding
        $this->app->singleton(TranslatorDriver::class, function () {
            $raw = (string) Config::get('simple-exception.translations.driver', 'simple-translation');

            // even if null, it will be default
            $driverEnum = TranslationDriver::tryFrom($raw) ?? TranslationDriver::SimpleTranslation;

            return $driverEnum->make();
        });

        // Console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeErrorRespCodeCommand::class,
                SyncTranslationsCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Publish config
        $configSrc = __DIR__ . '/../config/simple-exception.php';
        if (is_file($configSrc)) {
            $this->publishes([
                $configSrc => config_path('simple-exception.php'),
            ], 'simple-exception-config');
        }
    }
}