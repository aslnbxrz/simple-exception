<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;

/**
 * Sync per-enum translation files without overriding existing values.
 * Files: lang/{base_path}/{file}/{locale}.php
 */
class SyncTranslationsCommand extends Command
{
    protected $signature = 'sync:resp-translations
        {enum? : Enum class (e.g., UserRespCode or App\\Enums\\RespCodes\\UserRespCode). If omitted, use --all}
        {--locale= : Target locales (comma-separated); defaults to config("simple-exception.translations.locales")}
        {--all : Sync all enums found in configured directory}
        {--use-messages : (reserved) if your enum exposes custom message() per case}';

    protected $description = 'Sync translations for ThrowableEnum enums (per-enum files)';

    public function __construct(
        private readonly Filesystem          $fs,
        private readonly EnumTranslationSync $sync
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $enumArg = (string)($this->argument('enum') ?? '');
        $localesInput = (string)($this->option('locale') ?? '');
        $syncAll = (bool)$this->option('all');
        $useMessages = (bool)$this->option('use-messages'); // reserved

        $locales = EnumTranslationSync::normalizeLocales($localesInput);
        if (empty($locales)) {
            $this->error('No valid locale specified (use --locale=en or configure simple-exception.translations.locales).');
            return self::FAILURE;
        }

        // Build enum list
        $enums = [];
        if ($syncAll || $enumArg === '') {
            $enums = $this->discoverEnums();
            if (empty($enums)) {
                $this->warn('No enums found that implement ThrowableEnum in the configured directory.');
                return self::SUCCESS;
            }
            $this->info('📋 Found ' . count($enums) . ' enum(s).');
        } else {
            $fqcn = $this->normalizeEnumClass($enumArg);
            if (!$this->isValidEnum($fqcn)) {
                $this->error("Class {$fqcn} is not a valid enum implementing ThrowableEnum.");
                return self::FAILURE;
            }
            $enums = [$fqcn];
        }

        $errors = 0;
        $touched = [];

        foreach ($enums as $enumClass) {
            $this->line("🔄 {$enumClass}");

            $file = EnumTranslationSync::generateFileName($enumClass);
            $cases = EnumTranslationSync::enumCaseNames($enumClass);

            foreach ($locales as $locale) {
                try {
                    $langPath = EnumTranslationSync::translationFilePath($file, $locale);
                    $this->fs->ensureDirectoryExists(dirname($langPath));

                    $existing = $this->sync->readLangFile($langPath);
                    $updated = EnumTranslationSync::mergeCases($existing, $cases, $locale);

                    $this->fs->put($langPath, EnumTranslationSync::exportLang($updated));
                    $this->line("   ✅ {$file}/{$locale}.php updated");
                    $touched[] = $langPath;
                } catch (\Throwable $e) {
                    $this->line("   ❌ {$locale}: " . $e->getMessage());
                    $errors++;
                }
            }
        }

        $this->line('');
        $this->info('✅ Translation sync finished.');
        $this->line('   Enums: ' . count($enums));
        $this->line('   Locales: ' . implode(', ', $locales));
        $this->line('   Files touched: ' . count(array_unique($touched)));
        if ($errors > 0) {
            $this->warn("   Errors: {$errors}");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Discover enums that implement ThrowableEnum in configured directory */
    private function discoverEnums(): array
    {
        return $this->sync->getAvailableEnums();
    }

    /** Normalize enum class to FQCN (App\.. + RespCode suffix if missing) */
    private function normalizeEnumClass(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return $raw;
        }

        if (!preg_match('/RespCode$/i', $raw)) {
            $raw .= 'RespCode';
        }

        if (str_contains($raw, '\\')) {
            return $raw;
        }

        $relDir = (string)config('simple-exception.enum_generation.resp_codes_dir', 'Enums/RespCodes');
        $ns = 'App\\' . implode('\\', array_map(
                'ucfirst',
                array_filter(explode('/', trim($relDir, '/')))
            ));
        return $ns . '\\' . $raw;
    }

    /** Validate enum implements ThrowableEnum */
    private function isValidEnum(string $fqcn): bool
    {
        if (!class_exists($fqcn)) {
            return false;
        }
        $ref = new ReflectionClass($fqcn);
        if (!$ref->isEnum()) {
            return false;
        }
        foreach ($ref->getInterfaces() as $iface) {
            if ($iface->getName() === 'Aslnbxrz\\SimpleException\\Contracts\\ThrowableEnum') {
                return true;
            }
        }
        return false;
    }
}