<?php

namespace Aslnbxrz\SimpleException\Translations\Drivers;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Contracts\TranslatorDriver;
use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Translation driver that delegates to the Simple Translation package.
 *
 * Strategy:
 *  - keyFor(): Build an EN default sentence like "User not found error occurred."
 *  - translate(): Use ___($text, $scope, $locale) which auto-saves keys into DB
 *                 under the configured "exceptions" (or custom) scope.
 *  - No caching here; Simple Translation handles it.
 */
final readonly class SimpleTranslationDriver implements TranslatorDriver
{
    private string $scope;

    public function __construct()
    {
        $this->scope = Config::get('simple-exception.translations.drivers.simple-translation.scope', 'exceptions');
    }

    /**
     * Builds an English, human-readable default string for the enum case.
     * This string itself is used as the Simple Translation key.
     */
    public function keyFor(ThrowableEnum $enum): string
    {
        // e.g. UserRespCode::UserNotFound -> "user_not_found"
        $case = Str::snake($enum->name);

        // Return EN default message (e.g. "User not found error occurred.")
        return EnumTranslationSync::defaultMessage($case, 'en');
    }

    /**
     * Translates using Simple Translation's ___() helper.
     * Falls back to the English default if ___() is unavailable.
     */
    public function translate(ThrowableEnum $enum, ?string $locale = null): string
    {
        $locale ??= App::getLocale();

        // prefer config-defined scope; otherwise use constructor default
        $scope = (string)Config::get('simple-exception.translations.drivers.simple-translation.scope', $this->scope);

        $text = $this->keyFor($enum);

        // ___() — auto-save & translate via Simple Translation
        if (\function_exists('___')) {
            /** @var string $translated */
            $translated = ___($text, $scope, $locale);
            return $translated;
        }

        // If ___() doesn't exist (Simple Translation not installed), return default EN
        return $text;
    }
}