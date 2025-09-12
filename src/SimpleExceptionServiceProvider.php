<?php

namespace Aslnbxrz\SimpleException;

use Illuminate\Support\ServiceProvider;

class SimpleExceptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Load helpers (fallback if not composer "files")
        $helpers = __DIR__ . '/Support/helpers.php';
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        // Merge/preset config
        $path = __DIR__ . '/../config/simple-exception.php';

        if (file_exists($path)) {
            $this->mergeConfigFrom($path, 'simple-exception');
        } else {
            // Safe fallback so tests don't crash if config file is missing
            $defaults = [
                'response' => [
                    'template' => 'default',
                    'templates' => [
                        'default' => [
                            'success' => ':success',
                            'data' => ':data',
                            'message' => ':message',
                            'error' => [
                                'message' => ':message',
                                'code' => ':code',
                            ],
                            'meta' => ':meta',
                        ],
                    ],
                ],
                'default_error_code' => -1,
                'force_debug_meta' => null,
                'enum_generation' => ['resp_codes_dir' => 'Enums/RespCodes'],
                'translations' => ['base_path' => 'vendor/simple-exception'],
                'performance' => ['cache_messages' => true, 'cache_duration' => 3600],
            ];

            $existing = $this->app['config']->get('simple-exception', []);
            $this->app['config']->set('simple-exception', array_replace_recursive($defaults, $existing));
        }

        // (ixtiyoriy) Commandlarni registratsiya qiling:
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Aslnbxrz\SimpleException\Console\Commands\MakeErrorRespCodeCommand::class,
                \Aslnbxrz\SimpleException\Console\Commands\SyncTranslationsCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Publish config for end-users
        $this->publishes([
            __DIR__ . '/../config/simple-exception.php' => config_path('simple-exception.php'),
        ], 'config');

        // Publish stubs if you ship them
        $this->publishes([
            __DIR__ . '/Console/Commands/stubs/ErrorRespCode.stub' => base_path('stubs/ErrorRespCode.stub'),
        ], 'stubs');

        // Load translations if you ship package langs
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'simple-exception');
    }
}