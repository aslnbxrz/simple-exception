# Simple Exception - Laravel Package

A comprehensive exception handling package for Laravel with custom error responses, enum-based error codes, and automatic translation management.

## 🚀 Features

- **Enum-based Error Codes**: Create custom error response enums with automatic translation support
- **Helper Functions**: Use `error_if()`, `error_unless()`, `error()` without try-catch blocks
- **Automatic Translation Sync**: Sync enum cases with translation files automatically
- **Configurable**: Customize response structure, error codes, and behavior
- **Laravel Integration**: Full Laravel service provider and facade support

## 📦 Installation

```bash
composer require aslnbxrz/simple-exception
```

## ⚙️ Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=simple-exception-config
```

## 🎯 Quick Start

### Step 1: Create Your First Error Response Enum

```bash
php artisan make:error-resp-code User
```

This creates `app/Enums/UserRespCode.php`:

```php
<?php

namespace App\Enums;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Symfony\Component\HttpFoundation\Response;

enum UserRespCode: int implements ThrowableEnum
{
    case UnknownError = 2001;

    public function message(): string
    {
        $messages = match ($this) {
            self::UnknownError => 'An unknown error occurred',
        };

        if (function_exists('__')) {
            try {
                $translationKey = 'userrespcode.' . $this->getTranslationKey();
                $translated = __($translationKey, [], $messages);
                return $translated === $translationKey ? $messages : $translated;
            } catch (\Exception $e) {
                return $messages;
            }
        }

        return $messages;
    }

    private function getTranslationKey(): string
    {
        return $this->camelToSnake($this->name);
    }

    private function camelToSnake(string $string): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $string));
    }

    public function statusCode(): int
    {
        return $this->value;
    }

    public function httpStatusCode(): int
    {
        return match ($this) {
            self::UnknownError => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
```

### Step 2: Add More Cases to Your Enum

```php
enum UserRespCode: int implements ThrowableEnum
{
    case UnknownError = 2001;
    case UserNotFound = 2002;        // Add this
    case InvalidCredentials = 2003;  // Add this
    case AccessDenied = 2004;        // Add this

    public function message(): string
    {
        $messages = match ($this) {
            self::UnknownError => 'An unknown error occurred',
            self::UserNotFound => 'User not found',           // Add this
            self::InvalidCredentials => 'Invalid credentials', // Add this
            self::AccessDenied => 'Access denied',            // Add this
        };

        if (function_exists('__')) {
            try {
                $translationKey = 'userrespcode.' . $this->getTranslationKey();
                $translated = __($translationKey, [], $messages);
                return $translated === $translationKey ? $messages : $translated;
            } catch (\Exception $e) {
                return $messages;
            }
        }

        return $messages;
    }

    // ... rest of the methods
}
```

### Step 3: Sync Translations

```bash
php artisan sync:enum-translations UserRespCode
```

This creates `resources/lang/en/user_resp_code.php`:

```php
<?php

return [
    'unknown_error' => 'An unknown error occurred',
    'user_not_found' => 'User Not Found.',
    'invalid_credentials' => 'Invalid Credentials.',
    'access_denied' => 'Access Denied.',
];
```

### Step 4: Customize Translations

Edit the translation file:

```php
// resources/lang/en/user_resp_code.php
<?php

return [
    'unknown_error' => 'An unknown error occurred',
    'user_not_found' => 'User not found',
    'invalid_credentials' => 'Invalid credentials provided',
    'access_denied' => 'Access denied to this resource',
];
```

### Step 5: Use in Your Controllers

```php
<?php

namespace App\Http\Controllers;

use App\Enums\UserRespCode;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show($id)
    {
        $user = User::find($id);
        
        // Use helper functions - no try-catch needed!
        error_if(!$user, UserRespCode::UserNotFound);
        
        return response()->json(['user' => $user]);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        
        if (!Auth::attempt($credentials)) {
            error(UserRespCode::InvalidCredentials);
        }
        
        return response()->json(['message' => 'Login successful']);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        
        // Check permissions
        error_unless($user->can('update', $user), UserRespCode::AccessDenied);
        
        // Update user...
        return response()->json(['message' => 'User updated']);
    }
}
```

## 🌍 Multi-Language Support

### Add Uzbek Translations

```bash
php artisan sync:enum-translations UserRespCode --locale=uz
```

This creates `resources/lang/uz/user_resp_code.php`:

```php
<?php

return [
    'unknown_error' => 'Noma\'lum xatolik yuz berdi',
    'user_not_found' => 'Foydalanuvchi topilmadi',
    'invalid_credentials' => 'Noto\'g\'ri ma\'lumotlar',
    'access_denied' => 'Ruxsat yo\'q',
];
```

### Add Russian Translations

```bash
php artisan sync:enum-translations UserRespCode --locale=ru
```

## 🛠️ Advanced Usage

### Custom File Names

```bash
php artisan sync:enum-translations UserRespCode --file=user_errors
```

Creates `resources/lang/en/user_errors.php`

### Use Enum Message Method

If your enum has a `message()` method, use it for default translations:

```bash
php artisan sync:enum-translations UserRespCode --use-messages
```

### Custom Directory for Enums

Configure in `config/simple-exception.php`:

```php
'enum_generation' => [
    'resp_codes_dir' => 'Custom/ErrorCodes', // Default: 'Enums'
],
```

Then create enums:

```bash
php artisan make:error-resp-code User
# Creates: app/Custom/ErrorCodes/UserRespCode.php
# Namespace: App\Custom\ErrorCodes
```

## 📋 Available Commands

| Command | Description |
|---------|-------------|
| `php artisan make:error-resp-code {name}` | Create a new error response enum |
| `php artisan sync:enum-translations {enum}` | Sync translations for an enum |

## 🔧 Configuration Options

### Response Structure

```php
// config/simple-exception.php
'response' => [
    'success_key' => 'success',    // Default: 'success'
    'data_key' => 'data',          // Default: 'data'
    'error_key' => 'error',        // Default: 'error'
    'meta_key' => 'meta',          // Default: 'meta'
],
```

### Environment Detection

```php
'environment' => env('APP_ENV', 'production'),
'default_error_code' => -1,
```

## 🎨 Response Format

### Success Response
```json
{
    "success": true,
    "data": { ... },
    "error": null
}
```

### Error Response
```json
{
    "success": false,
    "data": null,
    "error": {
        "message": "User not found",
        "code": 2002
    }
}
```

### Error with Meta (Development)
```json
{
    "success": false,
    "data": null,
    "error": {
        "message": "User not found",
        "code": 2002
    },
    "meta": {
        "file": "/app/Http/Controllers/UserController.php",
        "line": 15,
        "trace": [...]
    }
}
```

## 🧪 Testing

```bash
composer test
```

## 📝 Changelog

### v1.0.3
- Added automatic translation sync
- Added `sync:enum-translations` command
- Improved enum generation with configurable directories
- Added multi-language support

### v1.0.2
- Added configurable enum generation directory
- Improved translation key generation
- Added automatic namespace generation

### v1.0.1
- Initial release
- Basic enum generation
- Helper functions
- Exception handling

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🙏 Credits

- [aslnbxrz](https://github.com/aslnbxrz)
- [webdeveloperr](https://github.com/webdeveloperr)

---

**Made with ❤️ for Laravel developers**