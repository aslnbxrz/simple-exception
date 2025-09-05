<?php

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Exceptions\ErrorResponse;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

if (!function_exists('error_response')) {
    /**
     * @throws ErrorResponse
     */
    function error_response(string|ThrowableEnum $message, string|int|null $code = null, ?Throwable $previous = null)
    {
        if ($message instanceof ThrowableEnum) {
            // For ThrowableEnum, pass it directly to ErrorResponse
            throw new ErrorResponse($message, $code, $previous);
        }

        $httpCode = Response::HTTP_INTERNAL_SERVER_ERROR;

        if ($previous === null) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $level1 = $trace[1] ?? $trace[0];
            $level0 = $trace[0];

            $prevMsg = sprintf(
                "Error in %s on line %s\nError in %s on line %s",
                $level1['file'] ?? 'unknown',
                $level1['line'] ?? '0',
                $level0['file'] ?? 'unknown',
                $level0['line'] ?? '0'
            );

            $previous = new Exception($prevMsg);
        }

        throw new ErrorResponse($message, $code, $previous, $httpCode);
    }
}

if (!function_exists('error')) {
    function error(string|ThrowableEnum $message): Closure
    {
        return fn() => error_response($message);
    }
}

if (!function_exists('persist_error')) {
    /**
     * @throws ErrorResponse
     */
    function persist_error($condition, string|ThrowableEnum $message, string|int|null $code): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? $trace[0];
        $previous = new Exception(sprintf(
            'Error in %s on line %d',
            $caller['file'] ?? 'unknown',
            $caller['line'] ?? 0
        ));

        $evaluated = is_callable($condition) ? $condition() : $condition;
        $evaluated = (bool)$evaluated;

        if ($evaluated) {
            error_response($message, $code, $previous);
        }
    }
}

if (!function_exists('error_if')) {
    /**
     * @throws ErrorResponse
     */
    function error_if($condition, string|ThrowableEnum $message, string|int|null $code = null): void
    {
        persist_error($condition, $message, $code);
    }
}

if (!function_exists('error_unless')) {
    /**
     * @throws ErrorResponse
     */
    function error_unless($condition, string|ThrowableEnum $message, string|int|null $code = null): void
    {
        persist_error(!$condition, $message, $code);
    }
}

if (!function_exists('is_dev')) {
    function is_dev(): bool
    {
        return !is_prod();
    }
}

if (!function_exists('is_prod')) {
    function is_prod(): bool
    {
        // Try to get from Laravel config if available
        if (class_exists(Config::class)) {
            try {
                $value = Config::get('simple-exception.environment');
                return $value === 'production';
            } catch (\Exception $e) {
                // Fallback to environment variable
            }
        }
        
        // Fallback to environment variable
        return ($_ENV['APP_ENV'] ?? 'production') === 'production';
    }
}

if (!function_exists('getLastFiveTraceEntries')) {
    function getLastFiveTraceEntries(Throwable $exception): string
    {
        $traceArray = $exception->getTrace();
        $lastFiveTraces = array_slice($traceArray, 0, 5);
        $traceString = '';

        foreach ($lastFiveTraces as $index => $trace) {
            $traceString .= "#{$index} " .
                ($trace['file'] ?? '[internal function]') .
                ':' . ($trace['line'] ?? 'N/A') . ' - ' .
                (isset($trace['class']) ? $trace['class'] . $trace['type'] : '') .
                $trace['function'] . "()\n";
        }

        return $traceString;
    }
}