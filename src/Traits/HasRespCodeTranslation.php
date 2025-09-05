<?php

namespace Aslnbxrz\SimpleException\Traits;

use Exception;
use Illuminate\Support\Str;
use ReflectionClass;

trait HasRespCodeTranslation
{
    /**
     * Get the translated message for the enum case.
     */
    public function message(): string
    {
        // Try to get translated message if Laravel is available
        if (function_exists('__')) {
            try {
                $translationKey = $this->getTranslationKey();
                
                // Try with simple-exception namespace
                $translated = __('simple-exception::' . $translationKey);
                
                // If translation exists and is different from key, return it
                if ($translated !== 'simple-exception::' . $translationKey) {
                    return $translated;
                }
                
                // Try without namespace as fallback
                $translated = __($translationKey);
                if ($translated !== $translationKey) {
                    return $translated;
                }
            } catch (Exception $e) {
                // If translation fails, fall back to generated message
            }
        }

        // Fallback to generated message from case name
        $caseName = str_replace('_', ' ', strtolower($this->name));
        return ucfirst($caseName) . ' error occurred.';
    }

    /**
     * Get the translation key for the enum case.
     */
    private function getTranslationKey(): string
    {
        // Get enum class name without 'RespCode' suffix
        $enumName = $this->getEnumName();
        return Str::snake($enumName) . '.' . Str::snake($this->name);
    }

    /**
     * Get the enum name without 'RespCode' suffix.
     */
    private function getEnumName(): string
    {
        $className = new ReflectionClass($this)->getShortName();
        // Remove 'RespCode' suffix if present
        if (str_ends_with($className, 'RespCode')) {
            return substr($className, 0, -8); // Remove 'RespCode' (8 characters)
        }
        return $className;
    }

}
