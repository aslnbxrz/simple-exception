<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class SyncTranslationsCommand extends Command
{
    protected $signature = 'sync:enum-translations 
                            {enum : The enum class name (e.g., UserRespCode or App\\Enums\\UserRespCode)}
                            {--locale=en : Target locale}
                            {--file= : Custom file name (default: snake case of enum name)}
                            {--use-messages : Use enum message() method for default text}';

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
}
