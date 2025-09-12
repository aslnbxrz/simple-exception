<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Helpers;

use Aslnbxrz\SimpleException\Enums\RespCodes\MainRespCode;
use Aslnbxrz\SimpleException\Exceptions\SimpleErrorResponse;
use Aslnbxrz\SimpleException\Exceptions\SimpleExceptionHandler;
use Aslnbxrz\SimpleException\Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_error_throws_with_enum_and_sets_http_code()
    {
        $this->expectException(SimpleErrorResponse::class);

        try {
            error(MainRespCode::AppVersionOutdated)();
        } catch (SimpleErrorResponse $e) {
            $this->assertEquals(426, $e->resolvedHttpCode());
            $this->assertEquals(426, $e->resolvedCode()); // enum value
            throw $e;
        }
    }

    public function test_error_response_wraps_string()
    {
        $this->expectException(SimpleErrorResponse::class);

        try {
            error_response('Oops', 1001);
        } catch (SimpleErrorResponse $e) {
            $this->assertSame('Oops', $e->getMessage());
            $this->assertEquals(1001, $e->resolvedCode());
            $this->assertNull($e->resolvedHttpCode());
            throw $e;
        }
    }

    public function test_error_if_and_unless()
    {
        $this->expectException(SimpleErrorResponse::class);

        try {
            error_if(true, MainRespCode::NotFound);
        } catch (SimpleErrorResponse $e) {
            $this->assertEquals(404, $e->resolvedHttpCode());
            throw $e;
        }

        $this->assertTrue(true);
    }

    public function test_error_unless_does_not_throw_when_true()
    {
        error_unless(true, MainRespCode::Forbidden);
        $this->assertTrue(true);
    }

    public function test_handler_builds_response_according_to_template()
    {
        config()->set('simple-exception.response.template', 'default');

        $json = SimpleExceptionHandler::handle('msg', 123, 500);
        $payload = $json->getData(true);

        $this->assertArrayHasKey('success', $payload);
        $this->assertArrayHasKey('error', $payload);
        $this->assertSame(false, $payload['success']);
        $this->assertSame('msg', $payload['error']['message']);
        $this->assertSame(123, $payload['error']['code']);
    }
}