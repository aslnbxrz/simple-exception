<?php

namespace Aslnbxrz\SimpleException\Support;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionEnum;
use ReflectionEnumBackedCase;

class EnumTranslationSync
{
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Sync translations for an enum class
     */
    public function sync(string $enumClass, string $locale = 'en', ?string $fileName = null, bool $useMessages = false): array
    {
        if (!enum_exists($enumClass)) {
            throw new \InvalidArgumentException("Enum class {$enumClass} does not exist.");
        }

        if (!is_subclass_of($enumClass, ThrowableEnum::class)) {
            throw new \InvalidArgumentException("Enum class {$enumClass} must implement ThrowableEnum interface.");
        }

        $fileName = $fileName ?? $this->generateFileName($enumClass);
        $filePath = $this->getTranslationFilePath($fileName, $locale);
        
        // Get existing translations
        $existingTranslations = $this->loadExistingTranslations($filePath);
        
        // Get enum cases and their translations
        $enumTranslations = $this->extractEnumTranslations($enumClass, $useMessages);
        
        // Merge translations (existing + new)
        $mergedTranslations = $this->mergeTranslations($existingTranslations, $enumTranslations);
        
        // Save translations
        $this->saveTranslations($filePath, $mergedTranslations);
        
        return [
            'file_path' => $filePath,
            'enum_class' => $enumClass,
            'locale' => $locale,
            'file_name' => $fileName,
            'total_cases' => count($enumTranslations),
            'new_cases' => count(array_diff_key($enumTranslations, $existingTranslations)),
            'updated_cases' => count(array_intersect_key($enumTranslations, $existingTranslations)),
        ];
    }

    /**
     * Generate file name from enum class
     */
    protected function generateFileName(string $enumClass): string
    {
        $className = class_basename($enumClass);
        
        // Special case for MainRespCode - use 'main'
        if ($className === 'MainRespCode') {
            return 'main';
        }
        
        // Remove RespCode suffix if present
        $className = preg_replace('/RespCode$/i', '', $className);
        
        return Str::snake($className);
    }

    /**
     * Get translation file path
     */
    protected function getTranslationFilePath(string $fileName, string $locale): string
    {
        $basePath = Config::get('simple-exception.translations.base_path', 'vendor/simple-exception');
        $langDir = lang_path("{$basePath}/{$fileName}");
        
        if (!$this->files->exists($langDir)) {
            $this->files->makeDirectory($langDir, 0755, true, true);
        }

        return $langDir . "/{$locale}.php";
    }

    /**
     * Load existing translations from file
     */
    protected function loadExistingTranslations(string $filePath): array
    {
        if (!$this->files->exists($filePath)) {
            return [];
        }

        try {
            $content = $this->files->get($filePath);
            $translations = eval('?>' . $content);
            return is_array($translations) ? $translations : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract translations from enum cases
     */
    protected function extractEnumTranslations(string $enumClass, bool $useMessages): array
    {
        $reflection = new ReflectionEnum($enumClass);
        $translations = [];

        foreach ($reflection->getCases() as $case) {
            $key = Str::snake($case->getName());
            
            if ($useMessages && method_exists($enumClass, 'message')) {
                // Try to get message from enum's message() method
                $caseInstance = $case->getValue();
                try {
                    $message = $caseInstance->message();
                    $translations[$key] = $message;
                } catch (\Exception $e) {
                    // Fallback to generated message
                    $translations[$key] = $this->generateDefaultMessage($key);
                }
            } else {
                // Generate default message
                $translations[$key] = $this->generateDefaultMessage($key);
            }
        }

        return $translations;
    }

    /**
     * Generate default message from key
     */
    protected function generateDefaultMessage(string $key): string
    {
        return Str::headline($key) . '.';
    }

    /**
     * Merge existing and new translations
     */
    protected function mergeTranslations(array $existing, array $new): array
    {
        // Keep existing translations and only add new ones (don't overwrite existing)
        return array_merge($new, $existing);
    }

    /**
     * Save translations to file
     */
    protected function saveTranslations(string $filePath, array $translations): void
    {
        $content = $this->generateTranslationFileContent($translations);
        $this->files->put($filePath, $content);
    }

    /**
     * Generate translation file content
     */
    protected function generateTranslationFileContent(array $translations): string
    {
        $content = "<?php\n\nreturn [\n";
        
        foreach ($translations as $key => $value) {
            $content .= "    '{$key}' => " . var_export($value, true) . ",\n";
        }
        
        $content .= "];\n";
        
        return $content;
    }

    /**
     * Get all available enum classes that implement ThrowableEnum
     */
    public function getAvailableEnums(): array
    {
        $enums = [];
        
        // Get enum directory from config
        $respCodesDir = Config::get('simple-exception.enum_generation.resp_codes_dir', 'Enums');
        $enumPath = app_path($respCodesDir);
        
        if ($this->files->exists($enumPath)) {
            $files = $this->files->glob($enumPath . '/*.php');
            
            foreach ($files as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                
                // Generate namespace from directory path
                $namespace = $this->generateNamespaceFromPath($respCodesDir);
                $className = $namespace . '\\' . $filename;
                
                if (enum_exists($className) && is_subclass_of($className, ThrowableEnum::class)) {
                    $enums[] = $className;
                }
            }
        }
        
        return $enums;
    }

    /**
     * Generate namespace from directory path
     */
    protected function generateNamespaceFromPath(string $respCodesDir): string
    {
        // Convert directory path to namespace
        $dirParts = explode('/', trim($respCodesDir, '/'));
        $namespace = 'App\\' . implode('\\', array_map('ucfirst', $dirParts));
        return $namespace;
    }
}
