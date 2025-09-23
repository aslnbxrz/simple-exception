<?php

namespace Aslnbxrz\SimpleException\Contracts;

/**
 * TranslatorDriver defines a contract for translation backends
 * used by the SimpleException package. Each driver must be able
 * to generate a unique translation key for an enum case and
 * resolve a localized message for it.
 */
interface TranslatorDriver
{
    /**
     * Generate a unique translation key for the given enum case.
     *
     * @param ThrowableEnum $enum The enum case representing an error code
     * @return string The translation key (e.g., "user.not_found")
     */
    public function keyFor(ThrowableEnum $enum): string;

    /**
     * Translate the given enum case into the specified locale.
     *
     * If no locale is provided, the current application locale should be used.
     * Drivers should return a fallback (default message) if no translation exists.
     *
     * @param ThrowableEnum $enum The enum case to translate
     * @param string|null $locale Optional locale code (e.g., "en", "uz")
     * @return string The translated message
     */
    public function translate(ThrowableEnum $enum, ?string $locale = null): string;
}