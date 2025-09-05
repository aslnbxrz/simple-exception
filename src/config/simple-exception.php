<?php

return [
    // Environment detection
    'environment' => env('APP_ENV', 'production'),
    
    // Default error code when no specific code is provided
    'default_error_code' => -1,
    
    // Response structure configuration
    'response' => [
        'success_key' => 'success',
        'data_key' => 'data',
        'error_key' => 'error',
        'meta_key' => 'meta',
    ],
    
    // Enum generation configuration
    'enum_generation' => [
        // Directory for generated error response codes
        'resp_codes_dir' => env('SIMPLE_EXCEPTION_RESP_CODES_DIR', 'Enums'),
    ],
    
    // Translation configuration
    'translations' => [
        // Base path for translation files
        'base_path' => env('SIMPLE_EXCEPTION_TRANSLATIONS_PATH', 'vendor/simple-exception'),
        
        // Structure: {base_path}/{enum_name}/{locale}/{enum_name}.php
        // Example: vendor/simple-exception/user/en/user.php
    ],
    
    // Performance optimizations
    'performance' => [
        // Cache error messages for better performance
        'cache_messages' => env('SIMPLE_EXCEPTION_CACHE_MESSAGES', true),
        
        // Cache duration in seconds (0 = forever)
        'cache_duration' => env('SIMPLE_EXCEPTION_CACHE_DURATION', 3600),
        
        // Enable debug mode for development
        'debug_mode' => env('SIMPLE_EXCEPTION_DEBUG', false),
    ],
    
    // Auto-registration settings
    'auto_register' => [
        // Automatically register exception handler
        'exception_handler' => true,
        
        // Automatically register helper functions
        'helpers' => true,
    ],
];