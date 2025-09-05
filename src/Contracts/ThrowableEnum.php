<?php

namespace Aslnbxrz\SimpleException\Contracts;

/** @property int value */
interface ThrowableEnum
{
    public function message(): string;

    public function statusCode(): int;

    public function httpStatusCode(): int;
}