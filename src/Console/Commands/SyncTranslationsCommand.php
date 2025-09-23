<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Aslnbxrz\SimpleTranslation\Models\AppText;
use Aslnbxrz\SimpleTranslation\Services\AppLanguageService;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;

class SyncTranslationsCommand extends Command
{
    protected $signature = 'sync:resp-translations
        {enum? : Enum class (e.g., UserRespCode or App\\Enums\\RespCodes\\UserRespCode). If omitted, use --all}
        {--locale= : Target locales (comma-separated); defaults to config("simple-exception.translations.locales")}
        {--all : Sync all enums found in configured directory}';

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

        // Enums ro‘yxati
        if ($syncAll || $enumArg === '') {
            $enums = array_values(array_unique($this->discoverEnums()));
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

        if ($driver !== 'simple-translation') {
            // Agar custom PHP-file driver bo‘lsa – eski yo‘l (per-enum .php lar)
            return $this->runPhpFilesDriver($enums, $locales);
        }

        // SIMPLE-TRANSLATION: DB-first, file-last

        $scope = (string)config('simple-exception.translations.drivers.simple-translation.scope', 'exceptions');
        $baseDir = (string)(config('simple-translation.translations.drivers.json-per-scope.base_dir') ?? lang_path());

        // 1) Avval BARCHA keylarni (EN default messsage = key) DBga upsert qilamiz (AppText) — translations rows shart emas
        $totalInserted = 0;

        foreach ($enums as $enumClass) {
            $this->line("🔄 {$enumClass}");
            $cases = EnumTranslationSync::enumCaseNames($enumClass);

            foreach ($cases as $caseName) {
                $key = EnumTranslationSync::defaultMessage(Str::snake($caseName), 'en');

                // AppText darajasida mavjud bo‘lmasa – create (faqat scope+text)
                AppText::query()->updateOrCreate(
                    ['scope' => $scope, 'text' => $key],
                    [] // nothing to update
                );
                $totalInserted++;
            }
        }

        // 2) Endi, faqat BIR MARTA, shu scope uchun BARCHA locale bo‘yicha JSON generatsiya qilamiz
        //    exportScope DBdan o‘qib, null bo‘lsa ham value = key qilib to‘ldiradi.
        try {
            $ok = AppLanguageService::exportScope($scope, $locales);
            if (!$ok) {
                $this->error("❌ Export failed for scope '{$scope}'. Check write permissions in: {$baseDir}");
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('❌ Export exception: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("✅ Synced. Seeded keys: {$totalInserted}, scope: {$scope}, locales: " . implode(', ', $locales));
        return self::SUCCESS;
    }

    /** PHP-file driver (custom) — eski yo‘l */
    private function runPhpFilesDriver(array $enums, array $locales): int
    {
        $errors = 0;
        $touched = [];

        foreach ($enums as $enumClass) {
            $this->line("🔄 {$enumClass}");
            $file = EnumTranslationSync::generateFileName($enumClass);
            $cases = EnumTranslationSync::enumCaseNames($enumClass);

            foreach ($locales as $locale) {
                try {
                    $langPath = EnumTranslationSync::translationFilePath($file, $locale);
                    $this->fs->ensureDirectoryExists(\dirname($langPath));

                    $existing = $this->sync->readLangFile($langPath);
                    $updated = EnumTranslationSync::mergeCases($existing, $cases, $locale);

                    $this->fs->put($langPath, EnumTranslationSync::exportLang($updated));
                    $this->line("   ✅ {$file}/{$locale}.php updated");
                    $touched[] = $langPath;
                } catch (\Throwable $e) {
                    $this->line("   ❌ {$file}/{$locale}.php → " . $e->getMessage());
                    $errors++;
                }
            }
        }

        if (!empty($touched)) {
            $this->line('');
            $this->line('Files touched: ' . count(array_unique($touched)));
        }
        if ($errors > 0) {
            $this->warn("Errors: {$errors}");
            return self::FAILURE;
        }

        $this->info('✅ PHP-file driver sync finished.');
        return self::SUCCESS;
    }

    private function discoverEnums(): array
    {
        return $this->sync->getAvailableEnums();
    }

    private function normalizeEnumClass(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return $raw;

        if (!preg_match('/RespCode$/i', $raw)) {
            $raw .= 'RespCode';
        }
        if (str_contains($raw, '\\')) return $raw;

        $relDir = (string)config('simple-exception.enum_generation.resp_codes_dir', 'Enums/RespCodes');
        $ns = 'App\\' . implode('\\', array_map(
                'ucfirst', array_filter(explode('/', trim($relDir, '/')))
            ));

        return $ns . '\\' . $raw;
    }

    private function isValidEnum(string $fqcn): bool
    {
        if (!class_exists($fqcn)) return false;

        $ref = new ReflectionClass($fqcn);
        if (!$ref->isEnum()) return false;

        foreach ($ref->getInterfaces() as $iface) {
            if ($iface->getName() === 'Aslnbxrz\\SimpleException\\Contracts\\ThrowableEnum') {
                return true;
            }
        }
        return false;
    }
}