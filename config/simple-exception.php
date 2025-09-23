<?php

return [
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
            // add your own templates here
        ],
    ],
    'default_error_code' => -1,
    'force_debug_meta' => null,
    'enum_generation' => [
        'resp_codes_dir' => env('SIMPLE_EXCEPTION_RESP_CODES_DIR', 'Enums/RespCodes'),
    ],
    'translations' => [
        'driver' => env('SIMPLE_EXCEPTION_TRANSLATION_DRIVER', 'simple-translation'),
        'drivers' => [
            'simple-translation' => [
                'scope' => 'exceptions'
            ],

            'custom' => [
                'base_path' => env('SIMPLE_EXCEPTION_TRANSLATIONS_PATH', 'vendor/simple-exceptions'),
                'locale_fallback' => 'en',
                'locales' => [
                    'en'
                ],
                'messages' => [
                    'patterns' => [
                        'en' => ':readable error occurred.',
                    ],
                ],
            ],
        ]
    ],
    'performance' => [
        'cache_messages' => env('SIMPLE_EXCEPTION_CACHE_MESSAGES', true),
        'cache_duration' => env('SIMPLE_EXCEPTION_CACHE_DURATION', 3600),
    ],
];