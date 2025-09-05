<?php

namespace Aslnbxrz\SimpleException\Enums;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Symfony\Component\HttpFoundation\Response;

enum MainRespCode: int implements ThrowableEnum
{
    case AppVersionOutdated = 426;
    case AppMissingHeaders = 1000;
    case AppWrongLanguage = 1001;
    case ValidationError = 1002;
    case AppInvalidDeviceModel = 1003;

    public function message(): string
    {
        $messages = match ($this) {
            self::AppVersionOutdated => 'Application version is outdated. Please update to the latest version.',
            self::AppMissingHeaders => 'Required headers are missing from the request.',
            self::AppWrongLanguage => 'Invalid or unsupported language specified.',
            self::ValidationError => 'The given data was invalid.',
            self::AppInvalidDeviceModel => 'Invalid or unsupported device model.',
        };

        // Try to get translated message if Laravel is available, fallback to default
        if (function_exists('__')) {
            try {
                $translated = __('simple-exception::main.' . $this->getTranslationKey(), [], $messages);
                return $translated === 'simple-exception::main.' . $this->getTranslationKey() ? $messages : $translated;
            } catch (\Exception $e) {
                // If translation fails, return default message
                return $messages;
            }
        }

        return $messages;
    }

    private function getTranslationKey(): string
    {
        return match ($this) {
            self::AppVersionOutdated => 'app_version_outdated',
            self::AppMissingHeaders => 'app_missing_headers',
            self::AppWrongLanguage => 'app_wrong_language',
            self::ValidationError => 'validation_error',
            self::AppInvalidDeviceModel => 'app_invalid_device_model',
        };
    }

    public function statusCode(): int
    {
        return $this->value;
    }

    public function httpStatusCode(): int
    {
        return match ($this) {
            self::AppVersionOutdated => Response::HTTP_UPGRADE_REQUIRED,
            self::AppMissingHeaders => Response::HTTP_BAD_REQUEST,
            self::AppWrongLanguage => Response::HTTP_NOT_ACCEPTABLE,
            self::ValidationError => Response::HTTP_UNPROCESSABLE_ENTITY,
            self::AppInvalidDeviceModel => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}