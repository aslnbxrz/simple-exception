<?php

namespace Aslnbxrz\SimpleException\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ErrorResponse extends Exception
{
    public function __construct(
        string               $message = '',
        int                  $code = 0,
        ?Throwable           $previous = null,
        private readonly int $httpCode = Response::HTTP_INTERNAL_SERVER_ERROR
    )
    {
        parent::__construct($message, $code, $previous);
    }

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