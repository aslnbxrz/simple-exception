<?php

namespace Aslnbxrz\SimpleException\Support;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionEnum;

readonly class EnumTranslationSync
{
    public function __construct(private Filesystem $files)
    {
    }

    /**
     * Main entry: sync single enum -> lang/vendor/simple-exception/{locale}/{file}.php
     */
    public function sync(string $enumClass, string $locale = 'en', ?string $fileName = null, bool $useMessages = false): array
    {
        if (!enum_exists($enumClass)) {
            throw new \InvalidArgumentException("Enum class {$enumClass} does not exist.");
        }
        if (!is_subclass_of($enumClass, ThrowableEnum::class)) {
            throw new \InvalidArgumentException("Enum class {$enumClass} must implement ThrowableEnum.");
        }

        $fileName = $fileName ?? $this->generateFileName($enumClass);

        // NEW canonical path (locale first)
        $newPath = $this->translationFilePath($fileName, $locale);

        // Auto-migrate from OLD path (file first) if exists
        $oldPath = $this->legacyTranslationFilePath($fileName, $locale);
        $legacy = $this->loadExistingTranslations($oldPath);
        $current = $this->loadExistingTranslations($newPath);

        // Build enum keys (always)
        $enumTranslations = $this->extractEnumTranslations($enumClass, $useMessages);

        // Merge order: current (new) + legacy (old) + enumDefaults (faqat yo‘qlar)
        $merged = $this->mergeTranslations($current, $legacy);
        $merged = $this->mergeTranslations($merged, $enumTranslations);

        // Ensure dir and save
        $this->files->ensureDirectoryExists(\dirname($newPath), 0755, true);
        $this->saveTranslations($newPath, $merged);

        // Clean up legacy file if there was one
        if ($legacy && $this->files->exists($oldPath)) {
            $this->files->delete($oldPath);
            // Agar papka bo‘sh qolsa – o‘chirib yuborish ixtiyoriy
            $legacyDir = \dirname($oldPath);
            if ($this->files->exists($legacyDir) && empty($this->files->files($legacyDir))) {
                $this->files->deleteDirectory($legacyDir);
            }
        }

        return [
            'file_path' => $newPath,
            'enum_class' => $enumClass,
            'locale' => $locale,
            'file_name' => $fileName,
            'total_cases' => \count($enumTranslations),
            'new_cases' => \count(\array_diff_key($enumTranslations, $current + $legacy)),
            'updated_cases' => \count(\array_intersect_key($enumTranslations, $current + $legacy)),
        ];
    }

    /** e.g. App\Enums\UserRespCode => 'user', MainRespCode => 'main' */
    protected function generateFileName(string $enumClass): string
    {
        $short = class_basename($enumClass);
        if ($short === 'MainRespCode') {
            return 'main';
        }
        $short = \preg_replace('/RespCode$/i', '', $short);
        return Str::snake($short);
    }

    /** NEW canonical path: lang/vendor/simple-exception/{locale}/{file}.php */
    protected function translationFilePath(string $file, string $locale): string
    {
        $base = Config::get('simple-exception.translations.base_path', 'vendor/simple-exception');
        return lang_path("{$base}/{$locale}/{$file}.php");
    }

    /** OLD legacy path: lang/vendor/simple-exception/{file}/{locale}.php */
    protected function legacyTranslationFilePath(string $file, string $locale): string
    {
        $base = Config::get('simple-exception.translations.base_path', 'vendor/simple-exception');
        return lang_path("{$base}/{$file}/{$locale}.php");
    }

    protected function loadExistingTranslations(string $path): array
    {
        if (!$this->files->exists($path)) {
            return [];
        }
        try {
            $arr = include $path;
            return \is_array($arr) ? $arr : [];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function extractEnumTranslations(string $enumClass, bool $useMessages): array
    {
        $ref = new ReflectionEnum($enumClass);
        $out = [];
        foreach ($ref->getCases() as $case) {
            $key = Str::snake($case->getName());
            // default text (agar $useMessages true bo‘lsa – message() dan, hozircha oddiy)
            $out[$key] = $this->defaultMessage($key);
        }
        return $out;
    }

    protected function defaultMessage(string $snakeKey): string
    {
        $readable = \ucfirst(\str_replace('_', ' ', $snakeKey));
        return "{$readable} error occurred.";
    }

    /** merge: $a ni saqlab, $b dan yo‘q bo‘lganlarni qo‘shadi */
    protected function mergeTranslations(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            if (!\array_key_exists($k, $a)) {
                $a[$k] = $v;
            }
        }
        return $a;
    }

    protected function saveTranslations(string $path, array $translations): void
    {
        $this->files->put($path, $this->exportLang($translations));
    }

    /** Pretty: `<?php\n\nreturn [ ... ];`  (testlar shuni kutadi) */
    protected function exportLang(array $translations): string
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

    /** Qidirish (o‘zgarmagan) */
    public function getAvailableEnums(): array
    {
        $out = [];
        $respCodesDir = Config::get('simple-exception.enum_generation.resp_codes_dir', 'Enums/RespCodes');
        $enumPath = app_path(\trim($respCodesDir, '/'));

        if ($this->files->exists($enumPath)) {
            foreach ($this->files->glob($enumPath . '/*.php') as $file) {
                $class = 'App\\' . \str_replace(
                        ['/', '.php'],
                        ['\\', ''],
                        \trim($respCodesDir, '/')
                    ) . '\\' . \basename($file, '.php');

                if (enum_exists($class) && is_subclass_of($class, ThrowableEnum::class)) {
                    $out[] = $class;
                }
            }
        }
        return $out;
    }
}
