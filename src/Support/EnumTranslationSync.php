<?php

namespace Aslnbxrz\SimpleException\Support;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use Throwable;

/**
 * Centralized utilities for translation syncing & generation (no duplication).
 * Layout (per enum, per locale):
 *   lang/{base_path}/{file}/{locale}.php
 * Example:
 *   lang/simple-exception/auth/en.php
 */
readonly class EnumTranslationSync
{
    public function __construct(private Filesystem $files)
    {
    }

    /** Normalize locales (CLI option → config fallback → default ['en']) */
    public static function normalizeLocales(string $csv): array
    {
        $arr = array_values(array_unique(array_filter(array_map(
            fn ($s) => strtolower(trim($s)),
            explode(',', (string) $csv)
        ))));

        if (!empty($arr)) {
            return $arr;
        }

        $cfg = (array) config('simple-exception.translations.locales', []);
        $cfg = array_values(array_unique(array_map(
            fn ($s) => strtolower(trim($s)),
            $cfg
        )));

        return $cfg ?: ['en'];
    }

    /** Build default message from config-driven patterns */
    public static function defaultMessage(string $snakeKey, string $locale): string
    {
        $messagesCfg = (array) config('simple-exception.translations.messages', []);
        $patterns    = (array) ($messagesCfg['patterns'] ?? []);
        $fallback    = (string) config('simple-exception.translations.locale_fallback', 'en');

        $pattern = $patterns[$locale]
            ?? $patterns[$fallback]
            ?? ':readable error occurred.';

        $readable = ucfirst(str_replace('_', ' ', $snakeKey));

        return str_replace(':readable', $readable, $pattern);
    }

    /** CamelCase → snake_case */
    public static function toSnake(string $s): string
    {
        $s = preg_replace('/(?<!^)[A-Z]/', '_$0', $s);
        $s = strtolower($s);
        return str_replace('__', '_', $s);
    }

    /** Enum FQCN → `{file}` (RespCode suffix stripped, snake cased) */
    public static function generateFileName(string $enumClass): string
    {
        $short = class_basename($enumClass);
        $short = preg_replace('/RespCode$/i', '', $short);
        return self::toSnake($short);
    }

    /**
     * Target path for a single enum+locale file:
     *   lang/{base_path}/{file}/{locale}.php
     */
    public static function translationFilePath(string $file, string $locale): string
    {
        $base = (string) Config::get('simple-exception.translations.base_path', 'simple-exception');
        $base = trim($base, "/\\");
        return lang_path($base . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $locale . '.php');
    }

    /** Read a lang file (returns [] on failure) */
    public function readLangFile(string $path): array
    {
        if (!$this->files->exists($path)) {
            return [];
        }
        try {
            $data = include $path;
            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Merge pairs [[CaseName, code], ...] into flat array (no groups),
     * preserving existing keys (do not override).
     */
    public static function mergePairs(array $existing, array $pairs, string $locale): array
    {
        foreach ($pairs as [$case, $_]) {
            $key = self::toSnake($case);
            if (!array_key_exists($key, $existing)) {
                $existing[$key] = self::defaultMessage($key, $locale);
            }
        }
        ksort($existing);
        return $existing;
    }

    /**
     * Merge case list (["CaseA","CaseB",...]) into flat array, preserving existing keys.
     */
    public static function mergeCases(array $existing, array $cases, string $locale): array
    {
        foreach ($cases as $caseName) {
            $key = self::toSnake($caseName);
            if (!array_key_exists($key, $existing)) {
                $existing[$key] = self::defaultMessage($key, $locale);
            }
        }
        ksort($existing);
        return $existing;
    }

    /** Export as short-array PHP file */
    public static function exportLang(array $translations): string
    {
        ksort($translations);

        $body = var_export($translations, true);
        $body = preg_replace('/^array\s*\(/', '[', $body);
        $body = preg_replace('/\)\s*$/', ']', $body);

        return <<<PHP
<?php

return {$body};

PHP;
    }

    /** Discover enums that implement ThrowableEnum in configured directory */
    public function getAvailableEnums(): array
    {
        $out          = [];
        $respCodesDir = (string) Config::get('simple-exception.enum_generation.resp_codes_dir', 'Enums/RespCodes');
        $enumPath     = app_path(trim($respCodesDir, '/'));

        if ($this->files->exists($enumPath)) {
            foreach ($this->files->glob($enumPath . '/*.php') as $file) {
                $class = 'App\\' . str_replace(['/', '.php'], ['\\', ''], trim($respCodesDir, '/')) . '\\' . basename($file, '.php');
                if (enum_exists($class) && is_subclass_of($class, ThrowableEnum::class)) {
                    $out[] = $class;
                }
            }
        }
        return $out;
    }

    /** Enum → case names
     * @throws ReflectionException
     */
    public static function enumCaseNames(string $enumClass): array
    {
        if (class_exists(ReflectionEnum::class)) {
            $ref = new ReflectionEnum($enumClass);
            return array_map(fn ($c) => $c->getName(), $ref->getCases());
        }

        $ref   = new ReflectionClass($enumClass);
        $names = [];
        if (method_exists($ref, 'getCases')) {
            foreach ($ref->getCases() as $case) {
                $names[] = $case->getName();
            }
        }
        return $names;
    }
}