<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aslnbxrz\SimpleException\Enums\MainRespCode;
use Aslnbxrz\SimpleException\Exceptions\ErrorResponse;
use Aslnbxrz\SimpleException\Facades\SimpleException;

// Example 1: Using enum-based error codes
try {
    SimpleException::error(MainRespCode::AppVersionOutdated);
} catch (ErrorResponse $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "HTTP Status: " . $e->getStatusCode() . "\n";
}

// Example 2: Using custom error messages
try {
    SimpleException::error('Custom error message', 1001);
} catch (ErrorResponse $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Example 3: Conditional error handling
$user = (object)['isBlocked' => true];

try {
    SimpleException::errorIf($user->isBlocked, 'User is blocked', 1002);
} catch (ErrorResponse $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Example 4: Using errorUnless
$isValid = false;

try {
    SimpleException::errorUnless($isValid, 'Data is not valid', 1003);
} catch (ErrorResponse $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Example 5: Environment detection
echo "Is Development: " . (SimpleException::isDev() ? 'Yes' : 'No') . "\n";
echo "Is Production: " . (SimpleException::isProd() ? 'Yes' : 'No') . "\n";

// Example 6: Building custom responses
$response = SimpleException::buildResponse('Custom error', 1004, ['additional' => 'data']);
echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

// Example 7: Using closure
$errorClosure = SimpleException::errorClosure('Error from closure', 1005);
try {
    $errorClosure();
} catch (ErrorResponse $e) {
    echo "Closure Error: " . $e->getMessage() . "\n";
}

echo "\nAll examples completed successfully!\n";