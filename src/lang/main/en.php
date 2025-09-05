<?php

return [
    // Server errors
    'app_version_outdated' => 'Application version is outdated. Please update to the latest version.',
    'app_missing_headers' => 'Required headers are missing from the request.',
    'app_wrong_language' => 'Invalid or unsupported language specified.',
    'validation_error' => 'The given data was invalid.',
    'app_invalid_device_model' => 'Invalid or unsupported device model.',
    
    // Maintenance mode
    'maintenance_mode' => 'The application is currently in maintenance mode. Please try again later.',
    'service_unavailable' => 'Service is temporarily unavailable. Please try again later.',
    
    // Server errors
    'internal_server_error' => 'An internal server error occurred. Please try again later.',
    'bad_gateway' => 'Bad gateway. Please try again later.',
    'gateway_timeout' => 'Gateway timeout. Please try again later.',
    
    // Authentication errors
    'unauthorized' => 'You are not authorized to perform this action.',
    'forbidden' => 'Access to this resource is forbidden.',
    'not_found' => 'The requested resource was not found.',
    
    // Rate limiting
    'too_many_requests' => 'Too many requests. Please slow down.',
    'rate_limit_exceeded' => 'Rate limit exceeded. Please try again later.',
];
