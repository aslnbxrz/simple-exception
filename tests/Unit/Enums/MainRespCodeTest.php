<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Enums;

use Aslnbxrz\SimpleException\Enums\MainRespCode;
use Aslnbxrz\SimpleException\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class MainRespCodeTest extends TestCase
{
    public function test_app_version_outdated_values()
    {
        $this->assertEquals(426, MainRespCode::AppVersionOutdated->value);
        $this->assertEquals(Response::HTTP_UPGRADE_REQUIRED, MainRespCode::AppVersionOutdated->httpStatusCode());
        $this->assertEquals('Application version is outdated. Please update to the latest version.', MainRespCode::AppVersionOutdated->message());
    }

    public function test_app_missing_headers_values()
    {
        $this->assertEquals(1000, MainRespCode::AppMissingHeaders->value);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, MainRespCode::AppMissingHeaders->httpStatusCode());
        $this->assertEquals('Required headers are missing from the request.', MainRespCode::AppMissingHeaders->message());
    }

    public function test_app_wrong_language_values()
    {
        $this->assertEquals(1001, MainRespCode::AppWrongLanguage->value);
        $this->assertEquals(Response::HTTP_NOT_ACCEPTABLE, MainRespCode::AppWrongLanguage->httpStatusCode());
        $this->assertEquals('Invalid or unsupported language specified.', MainRespCode::AppWrongLanguage->message());
    }

    public function test_validation_error_values()
    {
        $this->assertEquals(1002, MainRespCode::ValidationError->value);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, MainRespCode::ValidationError->httpStatusCode());
        $this->assertEquals('The given data was invalid.', MainRespCode::ValidationError->message());
    }

    public function test_app_invalid_device_model_values()
    {
        $this->assertEquals(1003, MainRespCode::AppInvalidDeviceModel->value);
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, MainRespCode::AppInvalidDeviceModel->httpStatusCode());
        $this->assertEquals('Invalid or unsupported device model.', MainRespCode::AppInvalidDeviceModel->message());
    }
}