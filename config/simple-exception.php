<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API response configuration
    |--------------------------------------------------------------------------
    */
    'response' => [
        'template' => 'default',

        /*
        |----------------------------------------------------------------------
        | Response templates
        |----------------------------------------------------------------------
        | make your own response template using placeholders:
        | - :message  (string)  error message
        | - :code     (int)     error code
        | - :success  (bool)    success flag
        | - :data     (?array)  data
        | - :meta     (array)   metadata (debug meta if enabled)
        |
        | if debug meta is disabled :meta will be removed from response even if it is not null
        */
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

    /*
    |--------------------------------------------------------------------------
    | Default error code when nothing provided
    |--------------------------------------------------------------------------
    */
    'default_error_code' => -1,

    /*
    |--------------------------------------------------------------------------
    | Debug meta key (true | false | null)
    |--------------------------------------------------------------------------
    |
    | if null it will use app('config')->get('app.debug')
    |
    */
    'force_debug_meta' => null,

    /*
    |--------------------------------------------------------------------------
    | Enum generation configuration
    |--------------------------------------------------------------------------
    */
    'enum_generation' => [
        'resp_codes_dir' => env('SIMPLE_EXCEPTION_RESP_CODES_DIR', 'Enums/RespCodes'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation configuration
    |--------------------------------------------------------------------------
    */
    'translations' => [
        'base_path' => env('SIMPLE_EXCEPTION_TRANSLATIONS_PATH', 'vendor/simple-exception'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance optimizations
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'cache_messages' => env('SIMPLE_EXCEPTION_CACHE_MESSAGES', true),
        'cache_duration' => env('SIMPLE_EXCEPTION_CACHE_DURATION', 3600),
    ],
];