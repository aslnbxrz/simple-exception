<?php

namespace Aslnbxrz\SimpleException;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Exceptions\ErrorResponse;
use Aslnbxrz\SimpleException\Exceptions\ExceptionHandler;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class SimpleExceptionService
{
    protected ExceptionHandler $exceptionHandler;

    public function __construct(ExceptionHandler $exceptionHandler)
    {
        $this->exceptionHandler = $exceptionHandler;
    }

    /**
     * @throws ErrorResponse
     */
    public function error(string|ThrowableEnum $message, string|int|null $code = null, ?Throwable $previous = null): void
    {
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

            $previous = new \Exception($prevMsg);
        }

        // Let ErrorResponse handle the enum logic
        throw new ErrorResponse($message, $code, $previous);
    }

    public function errorClosure(string|ThrowableEnum $message): \Closure
    {
        return fn() => $this->error($message);
    }

    /**
     * @throws ErrorResponse
     */
    public function persistError($condition, string|ThrowableEnum $message, string|int|null $code): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? $trace[0];
        $previous = new \Exception(sprintf(
            'Error in %s on line %d',
            $caller['file'] ?? 'unknown',
            $caller['line'] ?? 0
        ));

        $evaluated = is_callable($condition) ? $condition() : $condition;
        $evaluated = (bool)$evaluated;

        if ($evaluated) {
            $this->error($message, $code, $previous);
        }
    }

    /**
     * @throws ErrorResponse
     */
    public function errorIf($condition, string|ThrowableEnum $message, string|int $code = 0): void
    {
        $this->persistError($condition, $message, $code);
    }

    /**
     * @throws ErrorResponse
     */
    public function errorUnless($condition, string|ThrowableEnum $message, string|int $code = 0): void
    {
        $this->persistError(!$condition, $message, $code);
    }

    public function isDev(): bool
    {
        return !$this->isProd();
    }

    public function isProd(): bool
    {
        $value = Config::get('simple-exception.environment');

        return $value === 'production';
    }

    public function getLastFiveTraceEntries(Throwable $exception): string
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

    public function buildResponse(string $message, string|int|null $code, mixed $meta = null): array
    {
        $successKey = Config::get('simple-exception.response.success_key', 'success');
        $dataKey = Config::get('simple-exception.response.data_key', 'data');
        $errorKey = Config::get('simple-exception.response.error_key', 'error');
        $metaKey = Config::get('simple-exception.response.meta_key', 'meta');

        $res = [
            $successKey => false,
            $dataKey => null,
            $errorKey => [
                'message' => mb_convert_encoding($message, 'UTF-8', 'UTF-8'),
                'code' => is_numeric($code) ? $code : mb_convert_encoding($code, 'UTF-8', 'UTF-8'),
            ],
        ];

        if ($meta) {
            $res[$metaKey] = $meta;
        }

        return $res;
    }

    public function errorResponse(
        string|array|Throwable|ErrorResponse|ThrowableEnum $message,
        string|int|null|ThrowableEnum                      $code = null,
                                                           $httpCode = null
    ): JsonResponse
    {
        return $this->exceptionHandler->errorResponse($message, $code, $httpCode);
    }

    public function validationErrorResponse(ValidationException $e): JsonResponse
    {
        return $this->exceptionHandler->validationErrorResponse($e);
    }

    public function maintenanceResponse(): JsonResponse
    {
        return $this->exceptionHandler->maintenanceResponse();
    }
}