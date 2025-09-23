<?php

namespace Aslnbxrz\SimpleException\Traits;

use Aslnbxrz\SimpleException\Contracts\TranslatorDriver;
use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Illuminate\Support\Str;

/**
 * Provides a `message()` implementation for ThrowableEnum via a pluggable TranslatorDriver.
 */
trait HasRespCodeTranslation
{
    /**
     * Get the translated message for the enum case.
     * Delegates to a TranslatorDriver (bound in the container).
     */
    public function message(): string
    {
        /**
         * If container has a driver, use it
         */
        if (app()->bound(TranslatorDriver::class)) {
            /** @var TranslatorDriver $driver */
            $driver = app(TranslatorDriver::class);
            return $driver->translate($this);
        }

        /**
         * Fallback: build EN default msg from case name (no saving, no cache)
         */
        $case = Str::snake($this->name);
        return EnumTranslationSync::defaultMessage($case, 'en');
    }
}
