<?php

namespace Aslnbxrz\SimpleException\Traits;

trait HasStatusCode
{
    /**
     * Get the status code for the enum case.
     */
    public function statusCode(): int
    {
        return $this->value;
    }
}