<?php

namespace Aslnbxrz\SimpleException\Traits;

trait HasStatusCode
{
    public function statusCode(): int
    {
        return $this->value;
    }
}
