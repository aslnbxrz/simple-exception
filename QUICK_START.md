# 🚀 Quick Start Guide - Simple Exception Package

Bu qo'llanma sizga Simple Exception package'ini ishlatishni step-by-step ko'rsatadi.

## 📋 Kerakli Narsalar

- Laravel 10+ yoki 11+
- PHP 8.2+
- Composer

## 🎯 Step 1: Package'ni O'rnatish

```bash
composer require aslnbxrz/simple-exception
```

## ⚙️ Step 2: Config Faylini Publish Qilish

```bash
php artisan vendor:publish --tag=simple-exception-config
```

Bu `config/simple-exception.php` faylini yaratadi.

## 🏗️ Step 3: Birinchi Enum'ni Yaratish

```bash
php artisan make:error-resp-code User
```

**Natija:** `app/Enums/UserRespCode.php` fayli yaratiladi.

## ✏️ Step 4: Enum'ga Case'lar Qo'shish

`app/Enums/UserRespCode.php` faylini oching va case'lar qo'shing:

```php
enum UserRespCode: int implements ThrowableEnum
{
    case UnknownError = 2001;
    case UserNotFound = 2002;        // ← Qo'shing
    case InvalidCredentials = 2003;  // ← Qo'shing
    case AccessDenied = 2004;        // ← Qo'shing

    public function message(): string
    {
        $messages = match ($this) {
            self::UnknownError => 'An unknown error occurred',
            self::UserNotFound => 'User not found',           // ← Qo'shing
            self::InvalidCredentials => 'Invalid credentials', // ← Qo'shing
            self::AccessDenied => 'Access denied',            // ← Qo'shing
        };

        // ... qolgan kod o'zgarishsiz qoladi
    }

    // ... qolgan method'lar o'zgarishsiz qoladi
}
```

## 🌍 Step 5: Translation'lar Yaratish

```bash
php artisan sync:enum-translations UserRespCode
```

**Natija:** `resources/lang/en/user_resp_code.php` fayli yaratiladi:

```php
<?php

return [
    'unknown_error' => 'An unknown error occurred',
    'user_not_found' => 'User Not Found.',
    'invalid_credentials' => 'Invalid Credentials.',
    'access_denied' => 'Access Denied.',
];
```

## ✏️ Step 6: Translation'larni O'zgartirish

`resources/lang/en/user_resp_code.php` faylini oching va o'zgartiring:

```php
<?php

return [
    'unknown_error' => 'An unknown error occurred',
    'user_not_found' => 'User not found',                    // ← O'zgartiring
    'invalid_credentials' => 'Invalid credentials provided', // ← O'zgartiring
    'access_denied' => 'Access denied to this resource',     // ← O'zgartiring
];
```

## 🎮 Step 7: Controller'da Ishlatish

`app/Http/Controllers/UserController.php` yarating:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\UserRespCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function show($id)
    {
        $user = User::find($id);
        
        // Helper function ishlatish - try-catch kerak emas!
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
        
        // Permission tekshirish
        error_unless($user->can('update', $user), UserRespCode::AccessDenied);
        
        // User'ni yangilash...
        return response()->json(['message' => 'User updated']);
    }
}
```

## 🌍 Step 8: Ko'p Tillilik Qo'shish

### O'zbekcha Translation

```bash
php artisan sync:enum-translations UserRespCode --locale=uz
```

`resources/lang/uz/user_resp_code.php` yaratiladi:

```php
<?php

return [
    'unknown_error' => 'Noma\'lum xatolik yuz berdi',
    'user_not_found' => 'Foydalanuvchi topilmadi',
    'invalid_credentials' => 'Noto\'g\'ri ma\'lumotlar',
    'access_denied' => 'Ruxsat yo\'q',
];
```

### Ruscha Translation

```bash
php artisan sync:enum-translations UserRespCode --locale=ru
```

## 🔄 Step 9: Yangi Case Qo'shganda

Enum'ga yangi case qo'shing:

```php
case EmailNotVerified = 2005;  // ← Yangi case
```

Translation'ni sync qiling:

```bash
php artisan sync:enum-translations UserRespCode
```

**Natija:** Yangi case avtomatik qo'shiladi, mavjud translation'lar saqlanadi:

```php
<?php

return [
    'unknown_error' => 'An unknown error occurred',
    'user_not_found' => 'User not found',                    // Saqlanadi ✅
    'invalid_credentials' => 'Invalid credentials provided', // Saqlanadi ✅
    'access_denied' => 'Access denied to this resource',     // Saqlanadi ✅
    'email_not_verified' => 'Email Not Verified.',          // Yangi qo'shildi ✅
];
```

## 🎯 Step 10: Boshqa Enum'lar Yaratish

```bash
# Post uchun enum
php artisan make:error-resp-code Post
php artisan sync:enum-translations PostRespCode

# Auth uchun enum
php artisan make:error-resp-code Auth
php artisan sync:enum-translations AuthRespCode --locale=uz
```

## 🧪 Step 11: Test Qilish

```bash
# Test endpoint'ga so'rov yuborish
curl http://your-app.test/api/users/999
# Response: {"success":false,"data":null,"error":{"message":"User not found","code":2002}}

curl http://your-app.test/api/users/1
# Response: {"success":true,"data":{"user":{...}},"error":null}
```

## 🎉 Tugadi!

Endi sizda:
- ✅ Enum-based error codes
- ✅ Automatic translation sync
- ✅ Helper functions (`error_if`, `error_unless`, `error`)
- ✅ Multi-language support
- ✅ Configurable response structure

## 🆘 Yordam

Agar muammo bo'lsa:

1. **Config tekshiring:** `config/simple-exception.php`
2. **Translation fayllar:** `resources/lang/en/`
3. **Enum fayllar:** `app/Enums/`
4. **Log fayllar:** `storage/logs/laravel.log`

## 📚 Qo'shimcha Ma'lumot

- [To'liq README](README.md)
- [Configuration](config/simple-exception.php)
- [GitHub Repository](https://github.com/aslnbxrz/simple-exception)

---

**Happy Coding! 🚀**
