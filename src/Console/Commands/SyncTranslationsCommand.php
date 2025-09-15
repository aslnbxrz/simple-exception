<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Illuminate\Console\Command;
use ReflectionClass;

/**
 * CLI: Sync translation files for one or many ThrowableEnum enums.
 *
 * Examples:
 *  php artisan sync:resp-translations App\\Enums\\RespCodes\\MainRespCode --locale=en,uz --use-messages
 *  php artisan sync:resp-translations --all --locale=en,ru
 */
class SyncTranslationsCommand extends Command
{
    protected $signature = 'sync:resp-translations
        {enum? : The enum class (e.g., UserRespCode or App\\Enums\\RespCodes\\UserRespCode). If omitted, use --all}
        {--locale=en : Target locale(s), comma-separated (e.g. "en,uz,ru")}
        {--file= : Custom file name (default: snake case of enum name)}
        {--use-messages : Use enum message() method for default text}
        {--all : Sync all enums found in configured directory}';

    protected $description = 'Sync translations for enum classes that implement ThrowableEnum';

    public function __construct(
        private readonly EnumTranslationSync $syncService,
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $enumArg = (string)($this->argument('enum') ?? '');
        $localesInput = (string)$this->option('locale');
        $fileName = $this->option('file');
        $useMessages = (bool)$this->option('use-messages');
        $syncAll = (bool)$this->option('all');

        $locales = $this->normalizeLocales($localesInput);
        if (empty($locales)) {
            $this->error('No valid locale specified (use --locale=en or --locale=en,uz,ru).');
            return self::FAILURE;
        }

        // Build enum list
        $enums = [];
        if ($syncAll || $enumArg === '') {
            $enums = $this->syncService->getAvailableEnums();
            if (empty($enums)) {
                $this->warn('No enums found that implement ThrowableEnum in configured directory.');
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

        $success = 0;
        $errors = 0;
        $files = [];

        foreach ($enums as $enumClass) {
            $this->line("🔄 {$enumClass}");
            foreach ($locales as $locale) {
                try {
                    $result = $this->syncService->sync(
                        enumClass: $enumClass,
                        locale: $locale,
                        fileName: $fileName,
                        useMessages: $useMessages
                    );
                    $files[] = $result['file_path'] ?? '';
                    $this->line("   ✅ {$result['file_name']}/{$locale}.php created/updated");
                    $success++;
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
        $this->line('   Files touched: ' . count(\array_filter(\array_unique($files))));
        if ($errors > 0) {
            $this->warn("   Errors: {$errors}");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Normalize locales for sync command or fall back to config('simple-exception.locales') */
    private function normalizeLocales(string $csv): array
    {
        $arr = array_values(array_unique(array_filter(array_map(
            fn($s) => strtolower(trim($s)), explode(',', $csv)
        ))));

        if (!empty($arr)) {
            return $arr;
        }

        $cfg = (array)config('simple-exception.messages.locales', []);
        $cfg = array_values(array_unique(array_map(fn($s) => strtolower(trim($s)), $cfg)));

        return $cfg ?: ['en'];
    }

    /**
     * Build FQCN:
     * - If already namespaced, return as-is (plus RespCode suffix if missing).
     * - If short name, assume configured namespace path under App\, append RespCode if missing.
     */
    private function normalizeEnumClass(string $raw): string
    {
        $raw = \trim($raw);
        if ($raw === '') {
            return $raw;
        }

        if (!\preg_match('/RespCode$/i', $raw)) {
            $raw .= 'RespCode';
        }

        if (\str_contains($raw, '\\')) {
            return $raw;
        }

        $relDir = (string)\config('simple-exception.enum_generation.resp_codes_dir', 'Enums/RespCodes');
        $ns = 'App\\' . \implode('\\', \array_map('ucfirst', \array_filter(\explode('/', \trim($relDir, '/')))));
        return $ns . '\\' . $raw;
    }

    /** Validate that class exists, is enum, and implements ThrowableEnum */
    private function isValidEnum(string $fqcn): bool
    {
        if (!\class_exists($fqcn)) {
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