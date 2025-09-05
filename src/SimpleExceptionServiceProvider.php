<?php

namespace Aslnbxrz\SimpleException;

use Aslnbxrz\SimpleException\Exceptions\ExceptionHandler;
use Aslnbxrz\SimpleException\Console\Commands\MakeErrorRespCodeCommand;
use Aslnbxrz\SimpleException\Console\Commands\SyncTranslationsCommand;
use Illuminate\Contracts\Debug\ExceptionHandler as LaravelExceptionHandler;
use Illuminate\Support\ServiceProvider;

class SimpleExceptionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/simple-exception.php',
            'simple-exception'
        );

        $this->app->singleton(LaravelExceptionHandler::class, function ($app) {
            return new ExceptionHandler($app);
        });

        $this->app->singleton('simple-exception', function ($app) {
            return new SimpleExceptionService($app->make(LaravelExceptionHandler::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/simple-exception.php' => config_path('simple-exception.php'),
            ], 'simple-exception-config');

            $this->publishes([
                __DIR__.'/lang' => $this->app->langPath('vendor/simple-exception'),
            ], 'simple-exception-lang');

            // Register Artisan commands
            $this->commands([
                MakeErrorRespCodeCommand::class,
                SyncTranslationsCommand::class,
            ]);
        }

        $this->loadTranslationsFrom(__DIR__.'/lang', 'simple-exception');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [LaravelExceptionHandler::class];
    }
}