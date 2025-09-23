<?php

namespace Aslnbxrz\SimpleException\Translations\Drivers;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Contracts\TranslatorDriver;
use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * File-based translator:
 *  - Hech qanday DB yoki ___() chaqirmaydi (read-only).
 *  - Matnlarni lang/{base_path}/{file}/{locale}.php fayllaridan o‘qiydi.
 *  - Topilmasa, config-dagi pattern asosida yoki EnumTranslationSync default’iga qaytadi.
 *
 * Fayl tuzilmasi:
 *   lang/{base_path}/{file}/{locale}.php
 * unda qaytariladigan massiv: ['user_not_found' => '...']
 */
final readonly class CustomFileDriver implements TranslatorDriver
{
    private string $basePath;
    private string $fallbackLocale;

    public function __construct()
    {
        $this->basePath = Config::get('simple-exception.translations.drivers.custom.base_path', 'vendor/simple-exceptions');
        $this->fallbackLocale = Config::get('simple-exception.translations.drivers.custom.locale_fallback', 'en');
    }

    /**
     * keyFor() — bu drayverda real “kalit” sifatida ishlatilmaydi,
     * lekin izchil bo‘lishi uchun EN default matnni qaytaramiz.
     */
    public function keyFor(ThrowableEnum $enum): string
    {
        $case = Str::snake($enum->name);

        // Avval driverga xos pattern bor-yo‘qligini tekshiramiz
        $patterns = (array)Config::get('simple-exception.translations.drivers.custom.messages.patterns', []);
        $locale = 'en';
        $pattern = $patterns[$locale] ?? null;

        if (is_string($pattern) && $pattern !== '') {
            $readable = ucfirst(str_replace('_', ' ', $case));
            return str_replace(':readable', $readable, $pattern);
        }

        // Agar berilmagan bo‘lsa — umumiy helperga qaytamiz
        return EnumTranslationSync::defaultMessage($case, 'en');
    }

    /**
     * translate() — lang faylidan o‘qiydi; bo‘lmasa pattern (yoki umumiy default).
     */
    public function translate(ThrowableEnum $enum, ?string $locale = null): string
    {
        $locale = $this->normalizeLocale($locale);

        $file = $this->fileFromEnum($enum);     // masalan: "user"
        $case = Str::snake($enum->name);        // masalan: "user_not_found"

        // lang/{base_path}/{file}/{locale}.php
        $path = $this->langFilePath($file, $locale);
        $map = $this->safeRequireArray($path);

        if (array_key_exists($case, $map) && is_string($map[$case]) && $map[$case] !== '') {
            return $map[$case];
        }

        // driver patternlari orqali built-in default
        $patterns = (array)Config::get('simple-exception.translations.drivers.custom.messages.patterns', []);
        $pattern = $patterns[$locale] ?? $patterns[$this->fallbackLocale] ?? null;

        if (is_string($pattern) && $pattern !== '') {
            $readable = ucfirst(str_replace('_', ' ', $case));
            return str_replace(':readable', $readable, $pattern);
        }

        // umumiy defaultga qaytamiz (EnumTranslationSync)
        return EnumTranslationSync::defaultMessage($case, $locale);
    }

    private function normalizeLocale(?string $locale): string
    {
        $loc = strtolower((string)($locale ?: app()->getLocale()));
        return $loc ?: $this->fallbackLocale;
    }

    private function fileFromEnum(ThrowableEnum $enum): string
    {
        // UserRespCode -> "user" (EnumTranslationSync bilan izchil)
        $short = (new \ReflectionClass($enum))->getShortName();
        $short = preg_replace('/RespCode$/i', '', $short);
        return Str::snake($short);
    }

    private function langFilePath(string $file, string $locale): string
    {
        $base = trim($this->basePath, "/\\");
        return lang_path($base . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . $locale . '.php');
    }

    /** include qilganda xatoga uchramaslik uchun xavfsiz o‘qish */
    private function safeRequireArray(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        try {
            $data = include $path;
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }
}