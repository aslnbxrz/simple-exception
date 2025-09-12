<?php

namespace Aslnbxrz\SimpleException\Support;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use ReflectionEnum;

/**
 * Syncs translation files for ThrowableEnum-backed enums.
 * Writes to: resources/lang/vendor/simple-exception/{locale}/{file}.php
 *
 * - Does NOT overwrite existing keys (safe merge).
 * - Can use enum->message() as default when requested.
 * - Minimizes IO: only writes if content actually changed.
 */
readonly class EnumTranslationSync
{
    public function __construct(private Filesystem $files)
    {
    }

    /**
     * Synchronize translations for a single enum class.
     *
     * @param class-string $enumClass FQCN of enum implementing ThrowableEnum
     * @param string $locale e.g. "en"
     * @param string|null $fileName custom filename, defaults to snake(enum-name-without-RespCode)
     * @param bool $useMessages use enum case ->message() if available as default
     * @return array{
     *   file_path:string, enum_class:string, locale:string, file_name:string,
     *   total_cases:int, new_cases:int, updated_cases:int
     * }
     * @throws FileNotFoundException
     */
    public function sync(
        string  $enumClass,
        string  $locale = 'en',
        ?string $fileName = null,
        bool    $useMessages = false
    ): array
    {
        if (!enum_exists($enumClass)) {
            throw new \InvalidArgumentException("Enum class {$enumClass} does not exist.");
        }
        if (!is_subclass_of($enumClass, ThrowableEnum::class)) {
            throw new \InvalidArgumentException("Enum class {$enumClass} must implement ThrowableEnum interface.");
        }

        $fileName = $fileName ?: $this->generateFileName($enumClass);
        $filePath = $this->translationFilePath($fileName, $locale);

        $existing = $this->loadArray($filePath);

        $cases = (new ReflectionEnum($enumClass))->getCases();
        $desired = []; // desired order equals enum declaration order

        foreach ($cases as $case) {
            $key = $this->toSnake($case->getName());

            if ($useMessages) {
                // Get the enum case object reliably
                $enumObj = constant($enumClass . '::' . $case->getName());
                $msg = method_exists($enumObj, 'message')
                    ? (string)$enumObj->message()
                    : $this->defaultMessage($key);
            } else {
                $msg = $this->defaultMessage($key);
            }

            $desired[$key] = $msg;
        }

        // Merge: keep existing values, only add missing keys
        $merged = $existing;
        $newCount = 0;
        foreach ($desired as $k => $v) {
            if (!array_key_exists($k, $merged)) {
                $merged[$k] = $v;
                $newCount++;
            }
        }

        // Order keys by enum declaration first, then legacy keys (stable upgrade)
        $ordered = [];
        foreach ($desired as $k => $_) {
            if (array_key_exists($k, $merged)) {
                $ordered[$k] = $merged[$k];
            }
        }
        foreach ($merged as $k => $v) {
            if (!array_key_exists($k, $ordered)) {
                $ordered[$k] = $v;
            }
        }

        // Only write if actually changed
        $newContent = $this->exportLang($ordered);
        $oldContent = $this->files->exists($filePath) ? $this->files->get($filePath) : null;

        if ($newContent !== $oldContent) {
            $this->files->ensureDirectoryExists(\dirname($filePath));
            $this->files->put($filePath, $newContent);
        }

        return [
            'file_path' => $filePath,
            'enum_class' => $enumClass,
            'locale' => $locale,
            'file_name' => $fileName,
            'total_cases' => \count($desired),
            'new_cases' => $newCount,
            'updated_cases' => 0, // we never overwrite existing values
        ];
    }

    /** File name from enum class: App\Enums\RespCodes\UserRespCode => "user" */
    protected function generateFileName(string $enumClass): string
    {
        $short = \class_basename($enumClass);
        $short = \preg_replace('/RespCode$/i', '', $short);
        return $this->toSnake($short);
    }

    /**
     * Target: resources/lang/vendor/simple-exception/{locale}/{file}.php
     */
    protected function translationFilePath(string $fileName, string $locale): string
    {
        $base = (string)Config::get('simple-exception.translations.base_path', 'vendor/simple-exception');
        // Normalize to locale-first layout: .../vendor/simple-exception/{locale}/{file}.php
        $dir = lang_path("{$base}/{$locale}");
        $this->files->ensureDirectoryExists($dir);

        return $dir . "/{$fileName}.php";
    }

    /** Safe-load language array */
    protected function loadArray(string $filePath): array
    {
        if (!$this->files->exists($filePath)) {
            return [];
        }
        try {
            $arr = include $filePath;
            return \is_array($arr) ? $arr : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** snake_case converter (framework-agnostic) */
    protected function toSnake(string $s): string
    {
        $s = \preg_replace('/(?<!^)[A-Z]/', '_$0', $s);
        $s = \strtolower($s);
        return \str_replace('__', '_', $s);
    }

    /** Default humanized message */
    protected function defaultMessage(string $snakeKey): string
    {
        $readable = \ucfirst(\str_replace('_', ' ', $snakeKey));
        return "{$readable} error occurred.";
    }

    /** Pretty short-array export (stable diffs) */
    private function exportLang(array $translations): string
    {
        // var_export ni short array'ga konvert qilamiz
        $body = var_export($translations, true);           // "array ( ... )"
        $body = preg_replace('/^array\s*\(/', '[', $body); // "[ ... )"
        $body = preg_replace('/\)\s*$/', ']', $body);      // "[ ... ]"

        return "<?php\n\nreturn {$body};\n";
    }

    /**
     * Discover all enums implementing ThrowableEnum in configured directory (recursive).
     * Only *RespCode.php files are considered.
     *
     * @return list<class-string>
     */
    public function getAvailableEnums(): array
    {
        $relDir = (string)Config::get('simple-exception.enum_generation.resp_codes_dir', 'Enums/RespCodes');
        $basePath = app_path(\trim($relDir, '/'));

        if (!$this->files->isDirectory($basePath)) {
            return [];
        }

        $files = $this->files->allFiles($basePath);
        $ns = 'App\\' . \implode('\\', \array_map('ucfirst', \array_filter(\explode('/', \trim($relDir, '/')))));

        $out = [];
        foreach ($files as $f) {
            $name = $f->getFilename();
            if (!\str_ends_with($name, 'RespCode.php')) {
                continue;
            }
            $class = $ns . '\\' . \str_replace('.php', '', $name);
            if (\class_exists($class) && \enum_exists($class) && \is_subclass_of($class, ThrowableEnum::class)) {
                $out[] = $class;
            }
        }
        \sort($out);
        return $out;
    }

    protected function generateTranslationFileContent(array $translations): string
    {
        $lines = [];
        $lines[] = "<?php";
        $lines[] = "";
        $lines[] = "return [";

        foreach ($translations as $key => $value) {
            $lines[] = "    '{$key}' => " . var_export($value, true) . ",";
        }

        $lines[] = "];";
        $lines[] = "";

        return implode("\n", $lines);
    }
}