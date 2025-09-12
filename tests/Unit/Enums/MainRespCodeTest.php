<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Enums;

use Aslnbxrz\SimpleException\Enums\RespCodes\MainRespCode;
use Aslnbxrz\SimpleException\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class MainRespCodeTest extends TestCase
{
    public function test_values_and_status_codes_are_correct()
    {
        $this->assertEquals(426, MainRespCode::AppVersionOutdated->value);
        $this->assertEquals(Response::HTTP_UPGRADE_REQUIRED, MainRespCode::AppVersionOutdated->httpStatusCode());

        $this->assertEquals(1002, MainRespCode::ValidationError->value);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, MainRespCode::ValidationError->httpStatusCode());

        $this->assertEquals(404, MainRespCode::NotFound->value);
        $this->assertEquals(Response::HTTP_NOT_FOUND, MainRespCode::NotFound->httpStatusCode());
    }

    public function test_message_returns_non_empty_string()
    {
        // We don’t assert exact sentence to avoid coupling with translations
        $this->assertIsString(MainRespCode::AppWrongLanguage->message());
        $this->assertNotSame('', MainRespCode::AppWrongLanguage->message());
    }
}