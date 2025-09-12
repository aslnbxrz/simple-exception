<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aslnbxrz\SimpleException\Enums\RespCodes\MainRespCode;
use Aslnbxrz\SimpleException\Exceptions\SimpleErrorResponse;

echo "Simple Exception Package Examples\n";
echo "================================\n\n";

// Example 1: Using enum-based error codes
echo "1. Enum-based error codes:\n";
echo "   AppVersionOutdated message: " . MainRespCode::AppVersionOutdated->message() . "\n";
echo "   AppVersionOutdated code: " . MainRespCode::AppVersionOutdated->statusCode() . "\n";
echo "   AppVersionOutdated HTTP status: " . MainRespCode::AppVersionOutdated->httpStatusCode() . "\n\n";

// Example 2: Creating ErrorResponse directly
echo "2. Creating ErrorResponse directly:\n";
try {
    throw new SimpleErrorResponse('Custom error message', 1001, null, 400);
} catch (SImpleErrorResponse $e) {
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   Code: " . $e->getCode() . "\n";
    echo "   HTTP Status: " . $e->getStatusCode() . "\n\n";
}

// Example 3: All available error codes
echo "3. All available error codes:\n";
foreach (MainRespCode::cases() as $case) {
    echo "   {$case->name}: {$case->message()} (Code: {$case->statusCode()}, HTTP: {$case->httpStatusCode()})\n";
}

echo "\nAll examples completed successfully!\n";