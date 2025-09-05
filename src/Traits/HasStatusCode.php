<?php

namespace Aslnbxrz\SimpleException\Traits;

use Symfony\Component\HttpFoundation\Response;

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