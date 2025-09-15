<?php

namespace Aslnbxrz\SimpleException\Support;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use Throwable;

/**
 * Centralized utilities for translation syncing & generation.
 * - Computes target lang paths using config("simple-exception.translations.base_path").
 * - Reads/writes locale files (one file per locale).
 * - Merges groups (per enum snake name) without overriding existing values.
 * - Provides locale normalization and default message generation.
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
            fn($s) => strtolower(trim($s)),
            explode(',', $csv)
        ))));

        if (!empty($arr)) {
            return $arr;
        }

        $cfg = (array)config('simple-exception.translations.locales', []);
        $cfg = array_values(array_unique(array_map(
            fn($s) => strtolower(trim($s)),
            $cfg
        )));

        return $cfg ?: ['en'];
    }

    /** Build default message from config-driven patterns and overrides */
    public static function defaultMessage(string $snakeKey, string $locale): string
    {
        $messagesCfg = (array)config('simple-exception.translations.messages', []);
        $patterns = (array)($messagesCfg['patterns'] ?? []);
        $fallback = (string)config('simple-exception.translations.locale_fallback', 'en');

        $pattern = $patterns[$locale]
            ?? $patterns[$fallback]
            ?? ':readable error occurred.';

        $readable = ucfirst(str_replace('_', ' ', $snakeKey));

        return str_replace(':readable', $readable, $pattern);
    }

    /** Convert CamelCase → snake_case */
    public static function toSnake(string $s): string
    {
        $s = preg_replace('/(?<!^)[A-Z]/', '_$0', $s);
        $s = strtolower($s);
        return str_replace('__', '_', $s);
    }

    /** Return absolute lang file path for a given locale (one file per locale) */
    public static function localeFilePath(string $locale): string
    {
        $base = (string)Config::get('simple-exception.translations.base_path', 'simple-exception');
        $base = trim($base, "/\\");
        return lang_path($base . DIRECTORY_SEPARATOR . $locale . '.php');
    }

    /** Read an existing locale lang file. Returns [] on any failure. */
    public function readLocaleFile(string $path): array
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
     * Merge entries into a group (enum snake name) without overriding existing values.
     * $pairs: [[CaseName, code], ...] — code is ignored here; only keys are created.
     */
    public static function mergePairsIntoGroup(array $existing, string $group, array $pairs, string $locale): array
    {
        $existing[$group] = isset($existing[$group]) && is_array($existing[$group])
            ? $existing[$group]
            : [];

        foreach ($pairs as [$case, $_]) {
            $key = self::toSnake($case);
            if (!array_key_exists($key, $existing[$group])) {
                $existing[$group][$key] = self::defaultMessage($key, $locale);
            }
        }

        ksort($existing[$group]);
        ksort($existing);

        return $existing;
    }

    /**
     * Merge case list (["CaseA","CaseB",...]) into a group without overriding existing values.
     */
    public static function mergeCasesIntoGroup(array $existing, string $group, array $cases, string $locale): array
    {
        $existing[$group] = isset($existing[$group]) && is_array($existing[$group])
            ? $existing[$group]
            : [];

        foreach ($cases as $caseName) {
            $key = self::toSnake($caseName);
            if (!array_key_exists($key, $existing[$group])) {
                $existing[$group][$key] = self::defaultMessage($key, $locale);
            }
        }

        ksort($existing[$group]);
        ksort($existing);

        return $existing;
    }

    /** Export a PHP array as a pretty short-array file */
    public static function exportLang(array $translations): string
    {
        // Top-level sort only (stable & minimal). If you need recursive, replace with a recursive ksort.
        ksort($translations);

        $body = var_export($translations, true);
        $body = preg_replace('/^array\s*\(/', '[', $body);
        $body = preg_replace('/\)\s*$/', ']', $body);

        return <<<PHP
<?php

return $body;

PHP;
    }

    /** Discover available enums that implement ThrowableEnum in configured directory */
    public function getAvailableEnums(): array
    {
        $out = [];
        $respCodesDir = (string)Config::get('simple-exception.enum_generation.resp_codes_dir', 'Enums/RespCodes');
        $enumPath = app_path(trim($respCodesDir, '/'));

        if ($this->files->exists($enumPath)) {
            foreach ($this->files->glob($enumPath . '/*.php') as $file) {
                $class = 'App\\' . str_replace(
                        ['/', '.php'],
                        ['\\', ''],
                        trim($respCodesDir, '/')
                    ) . '\\' . basename($file, '.php');

                if (enum_exists($class) && is_subclass_of($class, ThrowableEnum::class)) {
                    $out[] = $class;
                }
            }
        }
        return $out;
    }

    /** Extract enum case names (strings) from an enum FQCN
     * @throws ReflectionException
     */
    public static function enumCaseNames(string $enumClass): array
    {
        // PHP 8.1+ has ReflectionEnum; on older versions this package shouldn't run.
        if (class_exists(ReflectionEnum::class)) {
            $ref = new ReflectionEnum($enumClass);
            return array_map(fn($c) => $c->getName(), $ref->getCases());
        }

        // Fallback using ReflectionClass (best-effort for future compatibility)
        $ref = new ReflectionClass($enumClass);
        $names = [];
        if (method_exists($ref, 'getCases')) {
            foreach ($ref->getCases() as $case) {
                $names[] = $case->getName();
            }
        }
        return $names;
    }
}