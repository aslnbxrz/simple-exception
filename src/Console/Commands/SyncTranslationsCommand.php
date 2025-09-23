<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Sync translations for ThrowableEnum enums.
 *
 * Behavior by driver:
 * - simple-translation: seed keys via ___() for every enum case and locale,
 *   then export JSON for the configured scope (no PHP files written).
 * - custom: update per-enum PHP files lang/{base}/{file}/{locale}.php without
 *   overriding existing values.
 */
class SyncTranslationsCommand extends Command
{
    protected $signature = 'sync:resp-translations
        {enum? : Enum class (e.g., UserRespCode or App\\Enums\\RespCodes\\UserRespCode). If omitted, use --all}
        {--locale= : Target locales (comma-separated); defaults to config("simple-exception.translations.locales")}
        {--all : Sync all enums found in configured directory}
        {--use-messages : (reserved) if your enum exposes custom message() per case}';

    protected $description = 'Sync translations for ThrowableEnum enums';

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

        $locales = EnumTranslationSync::normalizeLocales($localesInput);
        if (empty($locales)) {
            $this->error('No valid locale specified (use --locale=en or configure simple-exception.translations.locales).');
            return self::FAILURE;
        }

        // Build enum list
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

        $driver = (string)config('simple-exception.translations.driver', 'simple-translation');
        $errors = 0;
        $touched = [];

        foreach ($enums as $enumClass) {
            $this->line("🔄 {$enumClass}");

            $cases = EnumTranslationSync::enumCaseNames($enumClass);

            if ($driver === 'simple-translation') {
                // Seed via SimpleTranslation (no PHP files)
                $scope = (string)config('simple-exception.translations.drivers.simple-translation.scope', 'exceptions');

                foreach ($cases as $caseName) {
                    // English default sentence used as key
                    $key = EnumTranslationSync::defaultMessage(Str::snake($caseName), 'en');
                    foreach ($locales as $locale) {
                        try {
                            if (function_exists('___')) {
                                ___($key, $scope, $locale);
                            }
                        } catch (\Throwable $e) {
                            $this->line("   ❌ {$locale}: " . $e->getMessage());
                            $errors++;
                        }
                    }
                }

                // Export scope JSONs once per command (safe to repeat)
                try {
                    if (class_exists(AppLanguageService::class)) {
                        AppLanguageService::exportScope($scope, $locales);
                    }
                    $this->line("   ✅ seeded to SimpleTranslation (scope: {$scope})");
                } catch (\Throwable $e) {
                    $this->line("   ❌ export: " . $e->getMessage());
                    $errors++;
                }

                continue;
            }

            // File-based driver: update per-enum PHP files
            $file = EnumTranslationSync::generateFileName($enumClass);
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
        if (!empty($touched)) {
            $this->line('   Files touched: ' . count(array_unique($touched)));
        }
        if ($errors > 0) {
            $this->warn("   Errors: {$errors}");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Discover enums that implement ThrowableEnum in configured directory. */
    private function discoverEnums(): array
    {
        return $this->sync->getAvailableEnums();
    }

    /** Normalize enum class to FQCN (App\.. + RespCode suffix if missing). */
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

    /** Validate that a class is an enum implementing ThrowableEnum. */
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