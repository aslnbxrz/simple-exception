<?php

namespace Aslnbxrz\SimpleException\Enums\RespCodes;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Traits\HasRespCodeTranslation;
use Aslnbxrz\SimpleException\Traits\HasStatusCode;
use Symfony\Component\HttpFoundation\Response;

enum MainRespCode: int implements ThrowableEnum
{
    use HasRespCodeTranslation, HasStatusCode;

    case AppVersionOutdated = 426;
    case AppMissingHeaders = 1000;
    case AppWrongLanguage = 1001;
    case ValidationError = 1002;
    case AppInvalidDeviceModel = 1003;

    // Maintenance mode
    case MaintenanceMode = 503;
    case ServiceUnavailable = 504;

    // Server errors
    case InternalServerError = 500;
    case BadGateway = 502;
    case GatewayTimeout = 505;

    // Authentication errors
    case Unauthorized = 401;
    case Forbidden = 403;
    case NotFound = 404;

    // Rate limiting
    case TooManyRequests = 429;
    case RateLimitExceeded = 430;

    protected function getDefaultMessage(): string
    {
        return match ($this) {
            self::AppVersionOutdated => 'Application version is outdated. Please update to the latest version.',
            self::AppMissingHeaders => 'Required headers are missing from the request.',
            self::AppWrongLanguage => 'Invalid or unsupported language specified.',
            self::ValidationError => 'The given data was invalid.',
            self::AppInvalidDeviceModel => 'Invalid or unsupported device model.',

            // Maintenance mode
            self::MaintenanceMode => 'The application is currently in maintenance mode. Please try again later.',
            self::ServiceUnavailable => 'Service is temporarily unavailable. Please try again later.',

            // Server errors
            self::InternalServerError => 'An internal server error occurred. Please try again later.',
            self::BadGateway => 'Bad gateway. Please try again later.',
            self::GatewayTimeout => 'Gateway timeout. Please try again later.',

            // Authentication errors
            self::Unauthorized => 'You are not authorized to perform this action.',
            self::Forbidden => 'Access to this resource is forbidden.',
            self::NotFound => 'The requested resource was not found.',

            // Rate limiting
            self::TooManyRequests => 'Too many requests. Please slow down.',
            self::RateLimitExceeded => 'Rate limit exceeded. Please try again later.',
        };
    }

    public function httpStatusCode(): int
    {
        return match ($this) {
            self::AppVersionOutdated => Response::HTTP_UPGRADE_REQUIRED,
            self::AppMissingHeaders => Response::HTTP_BAD_REQUEST,
            self::AppWrongLanguage => Response::HTTP_NOT_ACCEPTABLE,
            self::ValidationError => Response::HTTP_UNPROCESSABLE_ENTITY,
            self::AppInvalidDeviceModel, self::InternalServerError => Response::HTTP_INTERNAL_SERVER_ERROR,

            // Maintenance mode
            self::MaintenanceMode, self::ServiceUnavailable => Response::HTTP_SERVICE_UNAVAILABLE,

            // Server errors
            self::BadGateway => Response::HTTP_BAD_GATEWAY,
            self::GatewayTimeout => Response::HTTP_GATEWAY_TIMEOUT,

            // Authentication errors
            self::Unauthorized => Response::HTTP_UNAUTHORIZED,
            self::Forbidden => Response::HTTP_FORBIDDEN,
            self::NotFound => Response::HTTP_NOT_FOUND,

            // Rate limiting
            self::TooManyRequests => Response::HTTP_TOO_MANY_REQUESTS,
            self::RateLimitExceeded => Response::HTTP_TOO_MANY_REQUESTS,
        };
    }
}