<?php

namespace Aslnbxrz\SimpleException\Exceptions;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Enums\MainRespCode;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class ExceptionHandler extends Handler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Cached configuration values for better performance
     */
    private ?array $responseConfig = null;
    private ?int $defaultErrorCode = null;

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (Throwable $exception, Request $request) {
            if (!$request->is('api/*')) {
                return parent::render($request, $exception);
            }

            if (app()->isDownForMaintenance()) {
                return $this->maintenanceResponse();
            }

            return $this->handleApiException($exception);
        });
    }

    /**
     * Handle API exceptions based on exception type
     */
    private function handleApiException(Throwable $exception): JsonResponse
    {
        return match ($exception::class) {
            ThrottleRequestsException::class => $this->handleThrottleException($exception),
            ValidationException::class => $this->validationErrorResponse($exception),
            default => $this->handleGenericException($exception),
        };
    }

    /**
     * Handle throttle exceptions
     */
    private function handleThrottleException(ThrottleRequestsException $exception): JsonResponse
    {
        return $this->errorResponse(
            $exception->getMessage(),
            httpCode: HttpResponse::HTTP_TOO_MANY_REQUESTS
        );
    }

    /**
     * Handle generic exceptions
     */
    private function handleGenericException(Throwable $exception): JsonResponse
    {
        return $this->errorResponse(
            $exception,
            $this->getDefaultErrorCode(),
            HttpResponse::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * Get maintenance response
     */
    public function maintenanceResponse(): JsonResponse
    {
        $response = $this->buildResponse('Server is under maintenance.', 503);
        return Response::json($response, HttpResponse::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * Handle error response with optimized logic
     */
    public function errorResponse(
        string|array|Throwable|ErrorResponse|ThrowableEnum $message,
        string|int|null|ThrowableEnum $code = null,
        ?int $httpCode = null
    ): JsonResponse {
        [$processedMessage, $processedCode, $processedHttpCode] = $this->processErrorParameters(
            $message, $code, $httpCode
        );

        $response = $this->buildResponse($processedMessage, $processedCode, $this->getDebugMeta($message));
        
        return Response::json($response, $processedHttpCode);
    }

    /**
     * Process error parameters and extract values
     */
    private function processErrorParameters(
        string|array|Throwable|ErrorResponse|ThrowableEnum $message,
        string|int|null|ThrowableEnum $code,
        ?int $httpCode
    ): array {
        if ($message instanceof Throwable) {
            return $this->processThrowableMessage($message, $code, $httpCode);
        }

        if (is_array($message)) {
            return $this->processArrayMessage($message);
        }

        return $this->processSimpleMessage($message, $code, $httpCode);
    }

    /**
     * Process Throwable message
     */
    private function processThrowableMessage(Throwable $exception, $code, ?int $httpCode): array
    {
        $processedCode = $code instanceof ThrowableEnum ? $code->value : ($code ?? $exception->getCode());
        $processedHttpCode = $httpCode ?? $this->getExceptionHttpCode($exception);
        $processedMessage = $exception->getMessage();

        return [$processedMessage, $processedCode, $processedHttpCode];
    }

    /**
     * Process array message
     */
    private function processArrayMessage(array $message): array
    {
        return [
            $message[0] ?? 'Unknown error',
            $message[1] ?? $this->getDefaultErrorCode(),
            $message[2] ?? HttpResponse::HTTP_INTERNAL_SERVER_ERROR
        ];
    }

    /**
     * Process simple message
     */
    private function processSimpleMessage($message, $code, ?int $httpCode): array
    {
        return [
            $message,
            $code ?? $this->getDefaultErrorCode(),
            $httpCode ?? HttpResponse::HTTP_INTERNAL_SERVER_ERROR
        ];
    }

    /**
     * Get HTTP code from exception
     */
    private function getExceptionHttpCode(Throwable $exception): int
    {
        if (method_exists($exception, 'getHttpCode')) {
            return $exception->getHttpCode() ?: $exception->getCode() ?: HttpResponse::HTTP_INTERNAL_SERVER_ERROR;
        }

        return $exception->getCode() ?: HttpResponse::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
     * Get debug metadata for development
     */
    private function getDebugMeta($message): ?array
    {
        if (!($message instanceof Throwable) || !is_dev()) {
            return null;
        }

        return [
            'file' => $message->getFile(),
            'line' => $message->getLine(),
            'trace' => $message->getTrace(),
        ];
    }

    /**
     * Handle validation error response
     */
    public function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        $validationErrors = Collection::make($exception->validator->errors()->messages())
            ->mapWithKeys(fn($errors, $field) => [$field => $errors[0]]);

        $response = $this->buildResponse(
            'Validation error',
            MainRespCode::ValidationError->value,
            ['validation_errors' => $validationErrors]
        );

        return Response::json($response, HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Build standardized response array
     */
    public function buildResponse(string $message, string|int|null $code, mixed $meta = null): array
    {
        $config = $this->getResponseConfig();

        $response = [
            $config['success_key'] => false,
            $config['data_key'] => null,
            $config['error_key'] => [
                'message' => $this->sanitizeMessage($message),
                'code' => $this->sanitizeCode($code),
            ],
        ];

        if ($meta !== null) {
            $response[$config['meta_key']] = $meta;
        }

        return $response;
    }

    /**
     * Sanitize message for output
     */
    private function sanitizeMessage(string $message): string
    {
        return mb_convert_encoding($message, 'UTF-8', 'UTF-8');
    }

    /**
     * Sanitize code for output
     */
    private function sanitizeCode(string|int|null $code): string|int
    {
        return is_numeric($code) ? $code : mb_convert_encoding($code, 'UTF-8', 'UTF-8');
    }

    /**
     * Get cached response configuration
     */
    private function getResponseConfig(): array
    {
        if ($this->responseConfig === null) {
            $this->responseConfig = [
                'success_key' => $this->getFromConfig('simple-exception.response.success_key', 'success'),
                'data_key' => $this->getFromConfig('simple-exception.response.data_key', 'data'),
                'error_key' => $this->getFromConfig('simple-exception.response.error_key', 'error'),
                'meta_key' => $this->getFromConfig('simple-exception.response.meta_key', 'meta'),
            ];
        }

        return $this->responseConfig;
    }

    /**
     * Get cached default error code
     */
    private function getDefaultErrorCode(): int
    {
        if ($this->defaultErrorCode === null) {
            $this->defaultErrorCode = $this->getFromConfig('simple-exception.default_error_code', -1);
        }

        return $this->defaultErrorCode;
    }

    /**
     * Get configuration value with fallback
     */
    private function getFromConfig(string $key, mixed $default): mixed
    {
        if (function_exists('config')) {
            return \config($key, $default);
        }

        if (class_exists(Config::class)) {
            return Config::get($key, $default);
        }

        return $default;
    }
}