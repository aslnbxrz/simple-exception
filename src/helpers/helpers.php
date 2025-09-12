<?php

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Exceptions\SimpleErrorResponse;

/**
 * Exception maker from Enum or string
 *
 * @throws SimpleErrorResponse
 */

function error_response(string|ThrowableEnum $message, string|int|null $code = null, ?Throwable $previous = null): never
{
    if ($previous === null && (config('app.debug') ?? false)) {
        $t = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $site = $t[1] ?? $t[0] ?? null;
        $hint = $site
            ? sprintf('Error at %s:%s', $site['file'] ?? 'unknown', $site['line'] ?? 0)
            : 'Error site unknown';
        $previous = new RuntimeException($hint);
    }

    $http = $message instanceof ThrowableEnum ? $message->httpStatusCode() : null;

    throw new SimpleErrorResponse($message, $code, $previous, $http);
}

/**
 * Lazy variant: closure returns error_response
 */
function error(string|ThrowableEnum $message): Closure
{
    return static fn() => error_response($message);
}

/**
 * If condition is true, throws SimpleErrorResponse
 *
 * @throws SimpleErrorResponse
 */
function error_if(mixed $condition, string|ThrowableEnum $message, string|int|null $code = null): void
{
    $ok = is_callable($condition) ? (bool)$condition() : (bool)$condition;
    if ($ok) {
        error_response($message, $code);
    }
}

/**
 * If condition is false, throws SimpleErrorResponse
 *
 * @throws SimpleErrorResponse
 */
function error_unless(mixed $condition, string|ThrowableEnum $message, string|int|null $code = null): void
{
    error_if(!$condition, $message, $code);
}

/** Returns true if app is in production */
function is_prod(): bool
{
    $env = config('app.env') ?? ($_ENV['APP_ENV'] ?? 'production');
    return $env === 'production';
}

function is_dev(): bool
{
    return !is_prod();
}