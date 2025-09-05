<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aslnbxrz\SimpleException\Enums\MainRespCode;
use Aslnbxrz\SimpleException\Exceptions\ErrorResponse;

echo "🚀 Simple Exception Package - Real World Usage Examples\n";
echo "======================================================\n\n";

// Example 1: Controller'da ishlatish (try-catch shart emas!)
echo "1. Controller'da ishlatish:\n";
echo "   // UserController.php\n";
echo "   public function login(Request \$request)\n";
echo "   {\n";
echo "       // Try-catch shart emas!\n";
echo "       error_if(empty(\$request->username), MainRespCode::AppMissingHeaders);\n";
echo "       error_unless(\$this->userService->exists(\$request->username), MainRespCode::AppInvalidDeviceModel);\n";
echo "       \n";
echo "       // Agar error bo'lsa, avtomatik exception throw qilinadi\n";
echo "       // Va Laravel'ning exception handler'i uni handle qiladi\n";
echo "   }\n\n";

// Example 2: Service'da ishlatish
echo "2. Service'da ishlatish:\n";
echo "   // UserService.php\n";
echo "   public function validateUser(\$user)\n";
echo "   {\n";
echo "       error_if(\$user->isBlocked, 'User is blocked', 1001);\n";
echo "       error_unless(\$user->isActive, 'User is not active', 1002);\n";
echo "   }\n\n";

// Example 3: Middleware'da ishlatish
echo "3. Middleware'da ishlatish:\n";
echo "   // AuthMiddleware.php\n";
echo "   public function handle(\$request, Closure \$next)\n";
echo "   {\n";
echo "       error_unless(\$request->hasHeader('Authorization'), MainRespCode::AppMissingHeaders);\n";
echo "       return \$next(\$request);\n";
echo "   }\n\n";

// Example 4: Validation'da ishlatish
echo "4. Validation'da ishlatish:\n";
echo "   // Custom validation rule\n";
echo "   public function passes(\$attribute, \$value)\n";
echo "   {\n";
echo "       \$isValid = \$this->validateValue(\$value);\n";
echo "       error_unless(\$isValid, 'Invalid value provided', 1003);\n";
echo "       return true;\n";
echo "   }\n\n";

// Example 5: API Response format
echo "5. API Response format:\n";
echo "   // Error response avtomatik quyidagi formatda qaytariladi:\n";
$response = [
    'success' => false,
    'data' => null,
    'error' => [
        'message' => 'Application version is outdated. Please update to the latest version.',
        'code' => 426
    ],
    'meta' => null
];
echo "   " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

// Example 6: Custom ErrorRespCode yaratish
echo "6. Custom ErrorRespCode yaratish:\n";
echo "   // Terminal'da:\n";
echo "   php artisan make:error-resp-code AythRespCode\n\n";
echo "   // Bu quyidagi faylni yaratadi: app/Enums/AythRespCode.php\n";
echo "   // Keyin ishlatish:\n";
echo "   use App\\Enums\\AythRespCode;\n";
echo "   error_if(true, AythRespCode::InvalidUsername);\n\n";

// Example 7: Performance optimizations
echo "7. Performance optimizations:\n";
echo "   // .env faylida:\n";
echo "   SIMPLE_EXCEPTION_CACHE_MESSAGES=true\n";
echo "   SIMPLE_EXCEPTION_CACHE_DURATION=3600\n";
echo "   SIMPLE_EXCEPTION_DEBUG=false\n\n";

echo "✅ Barcha misollar ko'rsatildi!\n";
echo "📦 Package to'liq tayyor va ishlatishga tayyor!\n";