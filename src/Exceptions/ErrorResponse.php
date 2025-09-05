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
        int|ThrowableEnum     $code = 0,
        ?Throwable            $previous = null,
        ?int                  $httpCode = null
    )
    {
        // Handle ThrowableEnum
        if ($message instanceof ThrowableEnum) {
            $enum = $message;
            $message = $enum->message();
            $code = $enum->statusCode();
            $httpCode = $httpCode ?? $enum->httpStatusCode();
        } elseif ($code instanceof ThrowableEnum) {
            $enum = $code;
            $message = $message ?: $enum->message();
            $code = $enum->statusCode();
            $httpCode = $httpCode ?? $enum->httpStatusCode();
        }

        $this->httpCode = $httpCode ?? Response::HTTP_INTERNAL_SERVER_ERROR;
        
        parent::__construct($message, $code, $previous);
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