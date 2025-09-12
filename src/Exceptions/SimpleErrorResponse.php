<?php

namespace Aslnbxrz\SimpleException\Exceptions;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use RuntimeException;
use Throwable;

final class SimpleErrorResponse extends RuntimeException
{
    public function __construct(
        public readonly string|ThrowableEnum $payload,
        public readonly string|int|null      $codeOverride = null,
        ?Throwable                           $previous = null,
        public readonly ?int                 $httpCodeOverride = null
    )
    {
        $message = $payload instanceof ThrowableEnum
            ? (method_exists($payload, 'message') ? $payload->message() : $payload->name)
            : (string)$payload;

        $code = $codeOverride instanceof ThrowableEnum ? $codeOverride->value : (int)($codeOverride ?? 0);

        parent::__construct($message, $code, $previous);
    }

    public function resolvedHttpCode(): ?int
    {
        if ($this->httpCodeOverride !== null) {
            return $this->httpCodeOverride;
        }
        if ($this->payload instanceof ThrowableEnum && method_exists($this->payload, 'httpStatusCode')) {
            return $this->payload->httpStatusCode();
        }
        return null;
    }

    public function resolvedCode(): int|string|null
    {
        if ($this->codeOverride instanceof ThrowableEnum) {
            return $this->codeOverride->value;
        }
        if ($this->codeOverride !== null) {
            return $this->codeOverride;
        }
        if ($this->payload instanceof ThrowableEnum) {
            return $this->payload->value;
        }
        return null;
    }
}