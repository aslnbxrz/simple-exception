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
        if (is_file($configPath)) {
            $this->mergeConfigFrom($configPath, 'simple-exception');
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
                'messages' => [
                    'locales' => [],
                    'locale_fallback' => 'en',
                    'patterns' => ['en' => ':readable error occurred.'],
                    'overrides' => [],
                ],
            ];
            $existing = $this->app['config']->get('simple-exception', []);
            $this->app['config']->set('simple-exception', array_replace_recursive($defaults, $existing));
        }

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

        // Publish stubs if you ship them
        $stubSrc = __DIR__ . '/Console/Commands/stubs/ErrorRespCode.stub';
        if (is_file($stubSrc)) {
            $this->publishes([
                $stubSrc => base_path('stubs/ErrorRespCode.stub'),
            ], 'simple-exception-stubs');
        }

        // Publish translations
        $packageLangPath = __DIR__ . '/../resources/lang';
        if (is_dir($packageLangPath)) {
            $this->loadTranslationsFrom($packageLangPath, 'simple-exception');

            // Publish target (Laravel 9+: lang/, old: resources/lang/)
            $target = function_exists('lang_path')
                ? lang_path('vendor/simple-exception')
                : resource_path('lang/vendor/simple-exception');

            $this->publishes([
                $packageLangPath => $target,
            ], 'simple-exception-translations');
        }
    }
}