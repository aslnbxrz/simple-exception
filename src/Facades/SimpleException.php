<?php

namespace Aslnbxrz\SimpleException\Facades;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Exceptions\ErrorResponse;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;

/**
 * @method static void error(string|ThrowableEnum $message, string|int|null $code = null, ?\Throwable $previous = null)
 * @method static \Closure errorClosure(string|ThrowableEnum $message)
 * @method static void persistError($condition, string|ThrowableEnum $message, string|int|null $code)
 * @method static void errorIf($condition, string|ThrowableEnum $message, string|int $code = 0)
 * @method static void errorUnless($condition, string|ThrowableEnum $message, string|int $code = 0)
 * @method static bool isDev()
 * @method static bool isProd()
 * @method static string getLastFiveTraceEntries(\Throwable $exception)
 * @method static array buildResponse(string $message, string|int|null $code, mixed $meta = null)
 * @method static \Illuminate\Http\JsonResponse errorResponse(string|array|\Throwable|ErrorResponse|ThrowableEnum $message, string|int|null|ThrowableEnum $code = null, $httpCode = null)
 * @method static \Illuminate\Http\JsonResponse validationErrorResponse(\Illuminate\Validation\ValidationException $e)
 * @method static \Illuminate\Http\JsonResponse maintenanceResponse()
 */
class SimpleException extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'simple-exception';
    }
}