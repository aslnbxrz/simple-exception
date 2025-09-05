# Simple Exception

A comprehensive exception handling package for Laravel with custom error responses and enum-based error codes.

## Features

- 🚀 **Enum-based Error Codes**: Define error codes using PHP enums with built-in HTTP status codes
- 🎯 **Custom Error Responses**: Standardized JSON error response format
- 🌍 **Multi-language Support**: Built-in support for English, Russian, and Uzbek languages
- 🔧 **Laravel Integration**: Seamless integration with Laravel's exception handling
- 📦 **Facade Support**: Easy-to-use facade for quick access
- 🧪 **Comprehensive Testing**: Full test coverage with PHPUnit
- ⚡ **Performance Optimized**: Lightweight and fast

## Installation

You can install the package via Composer:

```bash
composer require aslnbxrz/simple-exception
```

## Laravel Integration

### Service Provider Registration

The package will automatically register itself. If you need to manually register it, add the service provider to your `config/app.php`:

```php
'providers' => [
    // ...
    Aslnbxrz\SimpleException\SimpleExceptionServiceProvider::class,
],
```

### Facade Registration

The facade is automatically registered. If you need to manually register it, add the alias to your `config/app.php`:

```php
'aliases' => [
    // ...
    'SimpleException' => Aslnbxrz\SimpleException\Facades\SimpleException::class,
],
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Aslnbxrz\SimpleException\SimpleExceptionServiceProvider" --tag="simple-exception-config"
```

This will create `config/simple-exception.php`:

```php
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

### Language Files

Publish the language files:

```bash
php artisan vendor:publish --provider="Aslnbxrz\SimpleException\SimpleExceptionServiceProvider" --tag="simple-exception-lang"
```

## Usage

### 🚀 Quick Start (No Try-Catch Required!)

```php
use App\Enums\AythRespCode; // Your custom enum

// Simple usage - no try-catch needed!
error_if(true, AythRespCode::InvalidUsername);
error_unless(false, AythRespCode::UserNotFound);
error(AythRespCode::AccessDenied);
```

### Creating Your Own Error Response Codes

```bash
# Create a new error response code enum
php artisan make:error-resp-code AythRespCode
```

This will create `app/Enums/AythRespCode.php` with ready-to-use error codes.

### Basic Error Handling

```php
use Aslnbxrz\SimpleException\Facades\SimpleException;
use Aslnbxrz\SimpleException\Enums\MainRespCode;

// Using enum
SimpleException::error(MainRespCode::AppVersionOutdated);

// Using string message
SimpleException::error('Custom error message', 1001);

// Using closure
$errorClosure = SimpleException::errorClosure('Error message');
$errorClosure();
```

### Conditional Error Handling

```php
// Throw error if condition is true
SimpleException::errorIf($user->isBlocked, 'User is blocked', 1002);

// Throw error unless condition is true
SimpleException::errorUnless($user->isActive, 'User is not active', 1003);

// Using callable conditions
SimpleException::errorIf(fn() => $request->has('invalid_param'), 'Invalid parameter', 1004);
```

### Custom Error Response

```php
use Aslnbxrz\SimpleException\Exceptions\ErrorResponse;
use Symfony\Component\HttpFoundation\Response;

// Create custom error response
$response = SimpleException::errorResponse(
    'Custom error message',
    1005,
    Response::HTTP_BAD_REQUEST
);
```

### Environment Detection

```php
// Check if in development
if (SimpleException::isDev()) {
    // Development-specific code
}

// Check if in production
if (SimpleException::isProd()) {
    // Production-specific code
}
```

### Building Custom Responses

```php
$response = SimpleException::buildResponse(
    'Error message',
    1006,
    ['additional' => 'data'] // Optional meta data
);
```

## Creating Custom Error Enums

Create your own error enums by implementing the `ThrowableEnum` interface:

```php
<?php

namespace App\Enums;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Symfony\Component\HttpFoundation\Response;

enum CustomErrorCode: int implements ThrowableEnum
{
    case UserNotFound = 2001;
    case InvalidCredentials = 2002;
    case AccessDenied = 2003;

    public function message(): string
    {
        return match ($this) {
            self::UserNotFound => 'User not found',
            self::InvalidCredentials => 'Invalid credentials provided',
            self::AccessDenied => 'Access denied',
        };
    }

    public function statusCode(): int
    {
        return $this->value;
    }

    public function httpStatusCode(): int
    {
        return match ($this) {
            self::UserNotFound => Response::HTTP_NOT_FOUND,
            self::InvalidCredentials => Response::HTTP_UNAUTHORIZED,
            self::AccessDenied => Response::HTTP_FORBIDDEN,
        };
    }
}
```

## API Response Format

The package returns standardized JSON responses:

```json
{
    "success": false,
    "data": null,
    "error": {
        "message": "Error message",
        "code": 1001
    },
    "meta": {
        "additional": "data"
    }
}
```

## Exception Handler Integration

The package provides a custom exception handler that automatically handles API routes. To use it, replace your default exception handler in `app/Exceptions/Handler.php`:

```php
<?php

namespace App\Exceptions;

use Aslnbxrz\SimpleException\Exceptions\ExceptionHandler as BaseExceptionHandler;

class Handler extends BaseExceptionHandler
{
    // Your custom exception handling logic
}
```

## Testing

Run the tests with:

```bash
composer test
```

Or with PHPUnit directly:

```bash
./vendor/bin/phpunit
```

## Available Error Codes

| Code | Enum | HTTP Status | Description |
|------|------|-------------|-------------|
| 426 | AppVersionOutdated | 426 Upgrade Required | Application version is outdated |
| 1000 | AppMissingHeaders | 400 Bad Request | Required headers are missing |
| 1001 | AppWrongLanguage | 406 Not Acceptable | Invalid language specified |
| 1002 | ValidationError | 422 Unprocessable Entity | Validation failed |
| 1003 | AppInvalidDeviceModel | 500 Internal Server Error | Invalid device model |

## Language Support

The package includes translations for:

- English (en)
- Russian (ru)
- Uzbek (uz)

To add your own language, create a language file in `lang/{locale}/main.php` and publish it.

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [aslnbxrz](https://github.com/aslnbxrz)
- [All Contributors](../../contributors)

## Support

If you discover any issues or have questions, please [open an issue](https://github.com/aslnbxrz/simple-exception/issues).