<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class SyncTranslationsCommand extends Command
{
    protected $signature = 'sync:resp-translations 
                            {enum? : The enum class name (e.g., UserRespCode or App\\Enums\\RespCodes\\UserRespCode). If not provided, syncs all enums.}
                            {--locale=en : Target locale}
                            {--file= : Custom file name (default: snake case of enum name)}
                            {--use-messages : Use enum message() method for default text}
                            {--all : Sync all available enums}';

    protected $description = 'Sync translations for enum classes that implement ThrowableEnum';

    protected EnumTranslationSync $syncService;

    public function __construct()
    {
        parent::__construct();
        $this->syncService = new EnumTranslationSync(new Filesystem());
    }

    public function handle(): int
    {
        $enumClass = $this->argument('enum');
        $locale = $this->option('locale');
        $fileName = $this->option('file');
        $useMessages = $this->option('use-messages');
        $syncAll = $this->option('all');

        // If no enum provided or --all flag, sync all enums
        if (!$enumClass || $syncAll) {
            return $this->syncAllEnums($locale, $useMessages);
        }

        // Normalize enum class name
        $enumClass = $this->normalizeEnumClass($enumClass);

        try {
            $this->info("🔄 Syncing translations for {$enumClass}...");
            $this->line("");

            $result = $this->syncService->sync($enumClass, $locale, $fileName, $useMessages);

            $this->displayResults($result);

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Sync all available enums
     */
    protected function syncAllEnums(string $locale, bool $useMessages): int
    {
        $this->info("🔄 Syncing translations for all available enums...");
        $this->line("");

        $availableEnums = $this->syncService->getAvailableEnums();

        if (empty($availableEnums)) {
            $this->warn("⚠️  No enum classes found that implement ThrowableEnum interface.");
            $this->line("Make sure your enums are in the configured directory and implement ThrowableEnum interface.");
            return 0;
        }

        $this->info("📋 Found " . count($availableEnums) . " enum(s):");
        foreach ($availableEnums as $enum) {
            $this->line("   • {$enum}");
        }
        $this->line("");

        // Get all available locales
        $locales = $this->getAvailableLocales();
        $this->info("🌍 Available locales: " . implode(', ', $locales));
        $this->line("");

        $successCount = 0;
        $errorCount = 0;
        $results = [];

        foreach ($availableEnums as $enumClass) {
            $this->line("🔄 Syncing {$enumClass}...");
            
            foreach ($locales as $currentLocale) {
                try {
                    $result = $this->syncService->sync($enumClass, $currentLocale, null, $useMessages);
                    $results[] = $result;
                    $successCount++;
                    
                    $this->line("   ✅ {$result['file_name']}/{$currentLocale}.php created/updated");
                } catch (\Exception $e) {
                    $this->error("   ❌ Error syncing {$enumClass} for {$currentLocale}: " . $e->getMessage());
                    $errorCount++;
                }
            }
        }

        $this->line("");
        $this->displaySummaryResults($successCount, $errorCount, $results);

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Get available locales
     */
    protected function getAvailableLocales(): array
    {
        // Default locales
        $defaultLocales = ['en', 'uz', 'ru'];
        
        // Check if Laravel has configured locales
        if (function_exists('config') && config('app.locale')) {
            $appLocale = config('app.locale');
            $fallbackLocale = config('app.fallback_locale', 'en');
            
            $locales = array_unique([$appLocale, $fallbackLocale]);
            
            // Add default locales if not already present
            foreach ($defaultLocales as $locale) {
                if (!in_array($locale, $locales)) {
                    $locales[] = $locale;
                }
            }
            
            return $locales;
        }
        
        return $defaultLocales;
    }

    /**
     * Normalize enum class name
     */
    protected function normalizeEnumClass(string $enumClass): string
    {
        // If it's just a class name without namespace, assume it's in App\Enums
        if (!str_contains($enumClass, '\\')) {
            // Add RespCode suffix if not present
            if (!preg_match('/RespCode$/i', $enumClass)) {
                $enumClass .= 'RespCode';
            }
            return "App\\Enums\\{$enumClass}";
        }

        return $enumClass;
    }

    /**
     * Display sync results
     */
    protected function displayResults(array $result): void
    {
        $this->info("✅ Translation sync completed successfully!");
        $this->line("");
        
        $this->line("📊 <comment>Summary:</comment>");
        $this->line("   📁 File: <info>{$result['file_path']}</info>");
        $this->line("   🏷️  Enum: <info>{$result['enum_class']}</info>");
        $this->line("   🌍 Locale: <info>{$result['locale']}</info>");
        $this->line("   📄 File Name: <info>{$result['file_name']}</info>");
        $this->line("");
        
        $this->line("📈 <comment>Statistics:</comment>");
        $this->line("   📝 Total Cases: <info>{$result['total_cases']}</info>");
        $this->line("   🆕 New Cases: <info>{$result['new_cases']}</info>");
        $this->line("   🔄 Updated Cases: <info>{$result['updated_cases']}</info>");
        $this->line("");

        if ($result['new_cases'] > 0) {
            $this->line("💡 <comment>Tip:</comment> New translation keys have been added. You can customize them in the translation file.");
        }

        if ($result['updated_cases'] > 0) {
            $this->line("⚠️  <comment>Note:</comment> Some existing translation keys were updated with new default values.");
        }
    }

    /**
     * Display summary results for all enums
     */
    protected function displaySummaryResults(int $successCount, int $errorCount, array $results): void
    {
        $this->info("✅ Translation sync completed!");
        $this->line("");
        
        $this->line("📊 <comment>Summary:</comment>");
        $this->line("   ✅ Successfully synced: <info>{$successCount}</info> enum(s)");
        
        if ($errorCount > 0) {
            $this->line("   ❌ Failed: <error>{$errorCount}</error> enum(s)");
        }
        
        $totalCases = array_sum(array_column($results, 'total_cases'));
        $totalNewCases = array_sum(array_column($results, 'new_cases'));
        
        $this->line("   📝 Total cases processed: <info>{$totalCases}</info>");
        $this->line("   🆕 New cases added: <info>{$totalNewCases}</info>");
        $this->line("");

        if ($successCount > 0) {
            $this->line("📁 <comment>Created/Updated files:</comment>");
            foreach ($results as $result) {
                $this->line("   • {$result['file_path']}");
            }
            $this->line("");
        }

        if ($errorCount === 0) {
            $this->line("🎉 All enums synced successfully!");
        } else {
            $this->line("⚠️  Some enums failed to sync. Check the errors above.");
        }
    }
}
