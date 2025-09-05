<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeErrorRespCodeCommand extends Command
{
    protected $signature = 'make:error-resp-code {name : The name of the error response code enum}';
    protected $description = 'Create a new error response code enum';

    public function handle()
    {
        $name = $this->argument('name');
        $className = ucfirst($name);
        
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

        $this->info("Error response code enum {$className} created successfully!");
        $this->line("File: {$filePath}");
        $this->line("");
        $this->line("Usage examples:");
        $this->line("error_if(true, {$className}::SomeError);");
        $this->line("error_unless(false, {$className}::AnotherError);");
        $this->line("error({$className}::CustomError);");

        return 0;
    }
}