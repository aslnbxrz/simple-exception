<?php

namespace Aslnbxrz\SimpleException\Exceptions;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SimpleExceptionHandler
{
    /** Cached config (flattened & fast lookup) */
    private static ?array $cfg = null;

    /**
     * Universal entrypoint:
     * - $payload: string|array|Throwable|ThrowableEnum
     * - $code: string|int|null|ThrowableEnum
     * - $httpCode: int|null
     */
    public static function handle(
        string|array|Throwable|ThrowableEnum $payload,
        string|int|null|ThrowableEnum        $code = null,
        ?int                                 $httpCode = null
    ): JsonResponse
    {
        [$message, $finalCode, $finalHttp, $exception] = self::normalize($payload, $code, $httpCode);

        $meta = null;
        if (self::shouldShowMeta() && $exception instanceof Throwable) {
            $meta = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ];
        }

        if ($exception instanceof Throwable) {
            Log::error('SimpleException', [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }

        $response = self::buildResponseUsingTemplate(
            templateName: self::cfg('response.template', 'default'),
            vars: [
                'success' => false,
                'data' => null,
                'message' => self::sanitizeStr($message),
                'code' => self::sanitizeCode($finalCode),
                'meta' => $meta,
            ],
        );

        return response()->json($response, $finalHttp);
    }

    /**
     * Normalize input parameters.
     * @return array{0:string,1:int|string,2:int,3:Throwable|null}
     */
    private static function normalize(
        string|array|Throwable|ThrowableEnum $payload,
        string|int|null|ThrowableEnum        $code,
        ?int                                 $httpCode
    ): array
    {
        // SimpleErrorResponse
        if ($payload instanceof SimpleErrorResponse) {
            $msg = $payload->getMessage();
            $finalCode = $payload->resolvedCode() ?? ($code instanceof ThrowableEnum ? $code->value : $code);
            $finalHttp = $payload->resolvedHttpCode()
                ?? ($code instanceof ThrowableEnum && method_exists($code, 'httpStatusCode') ? $code->httpStatusCode() : null)
                ?? $httpCode
                ?? HttpResponse::HTTP_INTERNAL_SERVER_ERROR;

            return [
                $msg,
                self::fallbackCode($finalCode ?? self::cfg('default_error_code', -1)),
                self::fallbackHttp($finalHttp),
                $payload,
            ];
        }

        // ThrowableEnum
        if ($payload instanceof ThrowableEnum) {
            $msg = method_exists($payload, 'message') ? (string)$payload->message() : 'Error';
            $finalCode = $payload->value;
            $finalHttp = $httpCode ?? (method_exists($payload, 'httpStatusCode') ? $payload->httpStatusCode() : HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
            return [$msg, self::fallbackCode($finalCode), self::fallbackHttp($finalHttp), null];
        }

        // Throwable
        if ($payload instanceof Throwable) {
            $finalCode = $code instanceof ThrowableEnum ? $code->value : ($code ?? self::cfg('default_error_code', -1));
            $finalHttp = $httpCode ?? self::exceptionHttpCode($payload);
            $msg = $payload->getMessage() ?: 'Unexpected server error';
            return [$msg, self::fallbackCode($finalCode), self::fallbackHttp($finalHttp), $payload];
        }

        // Array: [$message, $code?, $httpCode?]
        if (is_array($payload)) {
            $msg = (string)($payload[0] ?? 'Unknown error');
            $c = $payload[1] ?? ($code instanceof ThrowableEnum ? $code->value : ($code ?? self::cfg('default_error_code', -1)));
            $h = $payload[2] ?? ($code instanceof ThrowableEnum && method_exists($code, 'httpStatusCode')
                ? $code->httpStatusCode()
                : ($httpCode ?? HttpResponse::HTTP_INTERNAL_SERVER_ERROR));
            return [$msg, self::fallbackCode($c), self::fallbackHttp($h), null];
        }

        // String
        $msg = (string)$payload;
        $finalCode = $code instanceof ThrowableEnum ? $code->value : ($code ?? self::cfg('default_error_code', -1));
        $finalHttp = $httpCode ?? ($code instanceof ThrowableEnum && method_exists($code, 'httpStatusCode')
            ? $code->httpStatusCode()
            : HttpResponse::HTTP_INTERNAL_SERVER_ERROR);

        return [$msg, self::fallbackCode($finalCode), self::fallbackHttp($finalHttp), null];
    }

    /** Get http status code from exception */
    private static function exceptionHttpCode(Throwable $e): int
    {
        // 1) Symfony/Laravel HttpException'lar: to'g'ridan-to'g'ri status code olish
        if ($e instanceof HttpExceptionInterface) {
            try {
                $s = (int)$e->getStatusCode();
                if ($s > 0) {
                    return $s;
                }
            } catch (Throwable) {
                // ignore
            }
        }

        // 2) Laravel-typical exceptions mapping (no getStatusCode())
        // Authentication / Authorization
        if ($e instanceof AuthenticationException) {
            return 401; // Unauthenticated
        }
        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return 403; // Forbidden
        }

        // Routing / Model / Method
        if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            return 404; // Not Found
        }
        if ($e instanceof MethodNotAllowedHttpException) {
            return 405; // Method Not Allowed
        }

        // CSRF
        if ($e instanceof TokenMismatchException) {
            return 419; // Page Expired (Laravel convention)
        }

        // Rate limiting
        if ($e instanceof ThrottleRequestsException) {
            return 429; // Too Many Requests
        }

        // Validation: keep 422
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return 422; // Unprocessable Entity
        }

        // 3) Fallback
        return HttpResponse::HTTP_INTERNAL_SERVER_ERROR; // 500
    }

    /**
     * Build response using template.
     * - Placeholders: :message, :code, :success, :data, :meta
     * - If meta is not provided, it will be removed from template
     */
    private static function buildResponseUsingTemplate(string $templateName, array $vars): array
    {
        $templates = (array)self::cfg('response.templates', []);

        if (!isset($templates[$templateName]) || !is_array($templates[$templateName])) {
            $base = [
                'success' => $vars['success'],
                'data' => $vars['data'],
                'error' => [
                    'message' => $vars['message'],
                    'code' => $vars['code'],
                ],
            ];

            if ($vars['meta'] !== null) {
                $base['meta'] = $vars['meta'];
            }
            return $base;
        }

        $tree = $templates[$templateName];

        $showMeta = self::shouldShowMeta();
        if (!$showMeta) {
            $tree = self::removeMetaKeys($tree);
            $vars['meta'] = null;
        }

        return self::applyTemplate($tree, $vars);
    }

    /** Remove all ':meta' keys from template */
    private static function removeMetaKeys(array $node): array
    {
        $out = [];
        foreach ($node as $key => $val) {
            if (is_array($val)) {
                $child = self::removeMetaKeys($val);
                if ($child !== []) {
                    $out[$key] = $child;
                }
            } else {
                if ($val === ':meta') {
                    continue;
                }
                $out[$key] = $val;
            }
        }
        return $out;
    }

    /** Replace placeholders in template with actual values */
    private static function applyTemplate(array $template, array $vars): array
    {
        $map = [
            ':message' => $vars['message'] ?? null,
            ':code' => $vars['code'] ?? null,
            ':success' => $vars['success'] ?? null,
            ':data' => $vars['data'] ?? null,
            ':meta' => $vars['meta'] ?? null,
        ];

        $replace = static function ($value) use (&$replace, $map) {
            if (is_array($value)) {
                $res = [];
                foreach ($value as $k => $v) {
                    $replaced = $replace($v);
                    if ($replaced !== null) {
                        $res[$k] = $replaced;
                    }
                }
                return $res;
            }
            if (!is_string($value)) {
                return $value;
            }
            return array_key_exists($value, $map) ? $map[$value] : $value;
        };

        return $replace($template);
    }

    /** Config helpers */
    private static function cfg(?string $key = null, mixed $default = null): mixed
    {
        if (self::$cfg === null) {
            // flatten/config cache
            $resp = (array)config('simple-exception.response', []);
            self::$cfg = [
                'response.template' => $resp['template'] ?? 'default',
                'response.templates' => $resp['templates'] ?? [],
                'default_error_code' => (int)config('simple-exception.default_error_code', -1),
                'force_debug_meta' => config('simple-exception.force_debug_meta'),
            ];
        }
        if ($key === null) {
            return self::$cfg;
        }
        return self::$cfg[$key] ?? $default;
    }

    private static function shouldShowMeta(): bool
    {
        $forced = self::cfg('force_debug_meta', null);
        if ($forced === true) return true;
        if ($forced === false) return false;
        return (bool)config('app.debug', false);
    }

    /** Sanitizers & fallbacks */
    private static function sanitizeStr(string $s): string
    {
        return mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }

    private static function sanitizeCode(string|int|null $code): string|int
    {
        return is_numeric($code) ? (int)$code : self::sanitizeStr((string)$code);
    }

    private static function fallbackCode(string|int|null $code): string|int
    {
        return ($code === null || $code === '') ? self::cfg('default_error_code', -1) : $code;
    }

    private static function fallbackHttp(?int $http): int
    {
        return $http ?: HttpResponse::HTTP_INTERNAL_SERVER_ERROR;
    }
}