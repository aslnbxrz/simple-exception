# Installation Guide

This guide will help you install and configure the Simple Exception package in your Laravel application.

## Prerequisites

- PHP 8.2 or higher
- Laravel 10.0 or higher
- Composer

## Step 1: Install the Package

Install the package via Composer:

```bash
composer require aslnbxrz/simple-exception
```

## Step 2: Publish Configuration

Publish the configuration file to customize the package settings:

```bash
php artisan vendor:publish --provider="Aslnbxrz\SimpleException\SimpleExceptionServiceProvider" --tag="simple-exception-config"
```

This will create `config/simple-exception.php` in your project.

## Step 3: Publish Language Files (Optional)

If you want to customize the error messages or add new languages:

```bash
php artisan vendor:publish --provider="Aslnbxrz\SimpleException\SimpleExceptionServiceProvider" --tag="simple-exception-lang"
```

This will create language files in `lang/vendor/simple-exception/`.

## Step 4: Configure Environment Variables

Add these variables to your `.env` file:

```env
# Simple Exception Configuration
SIMPLE_EXCEPTION_CACHE_MESSAGES=true
SIMPLE_EXCEPTION_CACHE_DURATION=3600
SIMPLE_EXCEPTION_DEBUG=false
```

## Step 5: Create Your First Error Response Code

Create a custom error response code enum:

```bash
php artisan make:error-resp-code AythRespCode
```

This will create `app/Enums/AythRespCode.php` with sample error codes.

## Step 6: Use in Your Controllers

Now you can use the package in your controllers without try-catch blocks:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\AythRespCode;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function login(Request $request)
    {
        // No try-catch needed!
        error_if(empty($request->username), AythRespCode::InvalidUsername);
        error_unless($this->userService->exists($request->username), AythRespCode::UserNotFound);
        
        // Your business logic here
        return response()->json(['message' => 'Login successful']);
    }
}
```

## Step 7: Configure Exception Handler (Optional)

To use the package's exception handler for API routes, update your `app/Exceptions/Handler.php`:

```php
<?php

namespace App\Exceptions;

use Aslnbxrz\SimpleException\Exceptions\ExceptionHandler as BaseExceptionHandler;

class Handler extends BaseExceptionHandler
{
    // Your custom exception handling logic
}
```

## Step 8: Test the Installation

Create a test route to verify the installation:

```php
// routes/api.php
Route::get('/test-error', function () {
    error('Test error message', 1001);
});
```

Visit `/api/test-error` to see the error response.

## Configuration Options

### Basic Configuration

```php
// config/simple-exception.php
return [
    'environment' => env('APP_ENV', 'production'),
    'default_error_code' => -1,
    'response' => [
        'success_key' => 'success',
        'data_key' => 'data',
        'error_key' => 'error',
        'meta_key' => 'meta',
    ]
];
```

### Performance Configuration

```php
'performance' => [
    'cache_messages' => env('SIMPLE_EXCEPTION_CACHE_MESSAGES', true),
    'cache_duration' => env('SIMPLE_EXCEPTION_CACHE_DURATION', 3600),
    'debug_mode' => env('SIMPLE_EXCEPTION_DEBUG', false),
],
```

### Auto-registration Configuration

```php
'auto_register' => [
    'exception_handler' => true,
    'helpers' => true,
],
```

## Troubleshooting

### Common Issues

1. **Class not found errors**: Run `composer dump-autoload`
2. **Configuration not found**: Make sure you published the config file
3. **Language files not loading**: Check if you published the language files

### Debug Mode

Enable debug mode to see detailed error information:

```env
SIMPLE_EXCEPTION_DEBUG=true
```

## Next Steps

1. Create your custom error response codes using the Artisan command
2. Use the helper functions in your controllers and services
3. Customize the response format in the configuration
4. Add your own language translations

## Support

If you encounter any issues during installation, please:

1. Check the [README](README.md) for usage examples
2. Open an issue on [GitHub](https://github.com/aslnbxrz/simple-exception/issues)
3. Check the [Laravel documentation](https://laravel.com/docs) for Laravel-specific issues

## Examples

See the `examples/` directory for more usage examples:

- `simple-usage.php` - Basic usage examples
- `real-world-usage.php` - Real-world implementation examples

Happy coding! 🚀