<?php

namespace Aslnbxrz\SimpleException\Exceptions;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ErrorResponse extends Exception
{
    public function __construct(
        string|ThrowableEnum  $message = '',
        int|ThrowableEnum|null $code = 0,
        ?Throwable            $previous = null,
        ?int                  $httpCode = null
    )
    {
        $finalMessage = $message;
        $finalCode = $code;
        $finalHttpCode = $httpCode;

        // Handle ThrowableEnum as message
        if ($message instanceof ThrowableEnum) {
            $enum = $message;
            $finalMessage = $enum->message();
            $finalCode = $enum->statusCode();
            $finalHttpCode = $httpCode ?? $enum->httpStatusCode();
        }
        // Handle ThrowableEnum as code
        elseif ($code instanceof ThrowableEnum) {
            $enum = $code;
            $finalMessage = $message ?: $enum->message();
            $finalCode = $enum->statusCode();
            $finalHttpCode = $httpCode ?? $enum->httpStatusCode();
        }

        // If code is null, use 0 as default
        if ($finalCode === null) {
            $finalCode = 0;
        }

        $this->httpCode = $finalHttpCode ?? Response::HTTP_INTERNAL_SERVER_ERROR;
        
        parent::__construct($finalMessage, $finalCode, $previous);
    }

    private readonly int $httpCode;

    public function getStatusCode(): int
    {
        return $this->httpCode;
    }

    /**
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}