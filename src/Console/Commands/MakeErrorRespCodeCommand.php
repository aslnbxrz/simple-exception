<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class MakeErrorRespCodeCommand extends Command
{
    protected $signature = 'make:error-resp-code {name? : The name of the error response code enum}';
    protected $description = 'Create a new error response code enum';

    public function handle()
    {
        $name = $this->getEnumName();
        
        if (!$name) {
            $this->error('Please provide a name for the error response code enum.');
            return 1;
        }

        $className = $this->formatClassName($name);
        
        // Get directory from config
        $respCodesDir = $this->getRespCodesDirectory();
        $enumPath = app_path($respCodesDir);
        
        // Create directory if it doesn't exist
        if (!File::exists($enumPath)) {
            File::makeDirectory($enumPath, 0755, true);
            $this->info("📁 Created directory: {$enumPath}");
        }

        $filePath = $enumPath . '/' . $className . '.php';

        if (File::exists($filePath)) {
            $this->error("Error response code enum {$className} already exists!");
            return 1;
        }

        // Stub faylini o'qish
        $stub = File::get(__DIR__ . '/stubs/ErrorRespCode.stub');
        
        // Generate namespace from directory
        $namespace = $this->generateNamespace($respCodesDir);
        
        // Stub'ni replace qilish
        $content = str_replace('{{ClassName}}', $className, $stub);
        $content = str_replace('{{LowerName}}', strtolower($name), $content);
        $content = str_replace('{{LowerClassName}}', strtolower($className), $content);
        $content = str_replace('{{Namespace}}', $namespace, $content);

        // Faylni yozish
        File::put($filePath, $content);

        // Lang faylini yaratish
        $this->createLangFile($className, $respCodesDir);

        $this->info("✅ Error response code enum {$className} created successfully!");
        $this->line("📁 File: {$filePath}");
        $this->line("📦 Namespace: {$namespace}");
        $this->line("");
        $this->line("🚀 Usage examples:");
        $this->line("   error_if(true, {$className}::UnknownError);");
        $this->line("   error_unless(false, {$className}::UnknownError);");
        $this->line("   error({$className}::UnknownError);");
        $this->line("");
        $this->line("💡 Tip: You can add more cases to the enum as needed!");
        $this->line("⚙️  Config: Directory set to '{$respCodesDir}' in simple-exception config");

        return 0;
    }

    /**
     * Get enum name from user input
     */
    private function getEnumName(): ?string
    {
        $name = $this->argument('name');
        
        if ($name) {
            return $name;
        }

        // Interactive mode
        $this->line('Welcome to Error Response Code Generator! 🎉');
        $this->line('');
        
        do {
            $name = $this->ask('What would you like to name your error response code enum?');
            
            if (!$name) {
                $this->error('Name cannot be empty. Please try again.');
                continue;
            }
            
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $name)) {
                $this->error('Name must contain only letters and numbers, and start with a letter.');
                continue;
            }
            
            break;
        } while (true);

        return $name;
    }

    /**
     * Format class name with RespCode suffix
     */
    private function formatClassName(string $name): string
    {
        // Remove RespCode if already present
        $name = preg_replace('/RespCode$/i', '', $name);
        
        // Capitalize first letter
        $name = ucfirst($name);
        
        // Add RespCode suffix
        return $name . 'RespCode';
    }

    /**
     * Get response codes directory from config
     */
    private function getRespCodesDirectory(): string
    {
        $configDir = Config::get('simple-exception.enum_generation.resp_codes_dir', 'Enums');
        
        // If it's a relative path, make it relative to app_path
        if (!str_starts_with($configDir, '/')) {
            return $configDir;
        }
        
        // If it's an absolute path, return as is
        return $configDir;
    }

    /**
     * Generate namespace from directory path
     */
    private function generateNamespace(string $respCodesDir): string
    {
        // Convert directory path to namespace
        $dirParts = explode('/', trim($respCodesDir, '/'));
        $namespace = 'App\\' . implode('\\', array_map('ucfirst', $dirParts));
        return $namespace;
    }

    /**
     * Create language file for the enum
     */
    private function createLangFile(string $className, string $respCodesDir): void
    {
        $lowerClassName = strtolower($className);
        $langDir = lang_path("vendor/simple-exception/{$lowerClassName}");
        
        // Create lang directory if it doesn't exist
        if (!File::exists($langDir)) {
            File::makeDirectory($langDir, 0755, true, true);
        }

        $langFile = $langDir . '/en.php';
        
        // Don't overwrite existing lang file
        if (File::exists($langFile)) {
            $this->line("📝 Language file already exists: {$langFile}");
            return;
        }

        $langContent = $this->generateLangContent($className);
        File::put($langFile, $langContent);
        
        $this->line("📝 Language file created: {$langFile}");
    }

    /**
     * Generate language file content
     */
    private function generateLangContent(string $className): string
    {
        $lowerClassName = strtolower($className);
        
        return "<?php

return [
    'unknown_error' => 'An unknown error occurred',
    
    // Add more translations as needed:
    // 'not_found' => 'Resource not found',
    // 'validation_error' => 'Validation failed',
    // 'access_denied' => 'Access denied',
];
";
    }
}