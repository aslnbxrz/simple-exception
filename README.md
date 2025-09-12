# SimpleException – Laravel Package

A modern exception handling package for Laravel with **enum-based error codes**, **automatic translation sync**, and **clean JSON API responses**.

---

## 🚀 Features

- ✅ **Enum-based error codes** (e.g. `MainRespCode`, `UserRespCode`)
- ✅ **Helper functions**: `error()`, `error_if()`, `error_unless()`, `error_response()`
- ✅ **Automatic translation sync**: keep enum cases in sync with `lang/` files
- ✅ **Configurable**: response structure, error keys, caching
- ✅ **Laravel-ready**: Service provider, config publish, artisan commands
- ✅ Works with **Laravel 9 → 12+**

---

## 📦 Installation

```bash
composer require aslnbxrz/simple-exception
```

Publish config:

```bash
php artisan vendor:publish --tag=simple-exception-config
```

This creates `config/simple-exception.php`.

---

## ⚙️ Configuration

### Example

```php
'response' => [
    'template' => 'default',

    'templates' => [
        'default' => [
            'success' => ':success',
            'data'    => ':data',
            'error'   => [
                'message' => ':message',
                'code'    => ':code',
            ],
            'meta'    => ':meta',
        ],
    ],
],

'default_error_code' => -1,

'enum_generation' => [
    'resp_codes_dir' => 'Enums/RespCodes', // relative to app/
],

'translations' => [
    'base_path' => 'vendor/simple-exception',
],
```

---

## 🎯 Quick Start

### Step 1 – Generate an Enum

```bash
php artisan make:resp-code User --cases="NotFound=404,Forbidden=403" --locale=en,uz
```

This creates:

- `app/Enums/RespCodes/UserRespCode.php`
- `lang/vendor/simple-exception/en/user.php`
- `lang/vendor/simple-exception/uz/user.php`

---

### Step 2 – Throw Errors

```php
use App\Enums\RespCodes\UserRespCode;

// Always throws SimpleErrorResponse
error(UserRespCode::NotFound);

// Conditional helpers
error_if(!$user, UserRespCode::NotFound);
error_unless($user->can('update'), UserRespCode::Forbidden);

// Custom string error
error_response('Custom failure', 1001);
```

---

### Step 3 – Example Controller

```php
use App\Enums\RespCodes\UserRespCode;

class UserController extends Controller
{
    public function show($id)
    {
        $user = User::find($id);

        error_if(!$user, UserRespCode::NotFound);

        return response()->json(['user' => $user]);
    }
}
```

---

## 🌍 Translation Management

### Sync all enums

```bash
php artisan sync:resp-translations --all
```

### Sync one enum

```bash
php artisan sync:resp-translations UserRespCode --locale=uz
```

Output:

```
📋 Found 1 enum(s).
🔄 Syncing App\Enums\RespCodes\UserRespCode
   ✅ lang/vendor/simple-exception/uz/user.php created/updated
```

### Example Translation File

`lang/vendor/simple-exception/en/user.php`

```php
<?php

return [
    'not_found'  => 'User not found',
    'forbidden'  => 'Access denied',
];
```

---

## 🎨 Response Format

### Success
```json
{
  "success": true,
  "data": { "id": 1, "name": "Bexruz" },
  "error": null,
  "meta": []
}
```

### Error
```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "User not found",
    "code": 404
  },
  "meta": []
}
```

### Error (Debug mode)
```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "User not found",
    "code": 404
  },
  "meta": {
    "file": "/app/Http/Controllers/UserController.php",
    "line": 15,
    "trace": [...]
  }
}
```

---

## 📋 Available Commands

| Command | Description |
|---------|-------------|
| `php artisan make:resp-code {name}` | Generate a new error enum (with translations) |
| `php artisan sync:resp-translations {enum?}` | Sync translations for a single enum or all enums |
| `php artisan vendor:publish --tag=simple-exception-config` | Publish package config |

---

## 🧪 Testing

```bash
composer test
```

Runs all package unit tests (artisan commands, exception handling, translation sync).

---

## 📝 Changelog

- **v1.1.0**
    - Added `make:resp-code` command with `--cases` and `--locale`
    - Unified translation folder structure: `lang/vendor/simple-exception/{locale}/{file}.php`
    - Improved helper functions (`error_if`, `error_unless`, etc.)
    - Cleaner `SimpleErrorResponse` API: `resolvedHttpCode()`, `resolvedCode()`

- **v1.0.x**
    - Initial release with enum-based exceptions and helpers

---

## 🤝 Contributing

1. Fork
2. Create feature branch
3. Commit changes
4. Open PR

---

## 📄 License

MIT © [aslnbxrz](https://github.com/aslnbxrz)
