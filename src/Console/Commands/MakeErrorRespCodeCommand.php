<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

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
        
        // App/Enums papkasini yaratish
        $enumPath = app_path('Enums');
        if (!File::exists($enumPath)) {
            File::makeDirectory($enumPath, 0755, true);
        }

        $filePath = $enumPath . '/' . $className . '.php';

        if (File::exists($filePath)) {
            $this->error("Error response code enum {$className} already exists!");
            return 1;
        }

        // Stub faylini o'qish
        $stub = File::get(__DIR__ . '/stubs/ErrorRespCode.stub');
        
        // Stub'ni replace qilish
        $content = str_replace('{{ClassName}}', $className, $stub);
        $content = str_replace('{{LowerName}}', strtolower($name), $content);

        // Faylni yozish
        File::put($filePath, $content);

        $this->info("✅ Error response code enum {$className} created successfully!");
        $this->line("📁 File: {$filePath}");
        $this->line("");
        $this->line("🚀 Usage examples:");
        $this->line("   error_if(true, {$className}::ExampleError);");
        $this->line("   error_unless(false, {$className}::ExampleError);");
        $this->line("   error({$className}::ExampleError);");
        $this->line("");
        $this->line("💡 Tip: You can add more cases to the enum as needed!");

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
}