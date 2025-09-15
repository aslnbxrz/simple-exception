<?php

namespace Aslnbxrz\SimpleException;

use Aslnbxrz\SimpleException\Console\Commands\MakeErrorRespCodeCommand;
use Aslnbxrz\SimpleException\Console\Commands\SyncTranslationsCommand;
use Illuminate\Support\ServiceProvider;

class SimpleExceptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Load helpers (fallback if not composer "files")
        $helpers = __DIR__ . '/Support/helpers.php';
        if (is_file($helpers)) {
            require_once $helpers;
        }

        // 2) Config merge
        $configPath = __DIR__ . '/../config/simple-exception.php';
        $this->mergeConfigFrom($configPath, 'simple-exception');

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