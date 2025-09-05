<?php

namespace Aslnbxrz\SimpleException\Tests\Unit;

use Aslnbxrz\SimpleException\Enums\RespCodes\MainRespCode;
use Aslnbxrz\SimpleException\Exceptions\ErrorResponse;
use Aslnbxrz\SimpleException\SimpleExceptionService;
use Aslnbxrz\SimpleException\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class SimpleExceptionServiceTest extends TestCase
{
    protected SimpleExceptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app('simple-exception');
    }

    public function test_error_throws_exception_with_enum()
    {
        $this->expectException(ErrorResponse::class);
        $this->expectExceptionMessage('Application version is outdated. Please update to the latest version.');
        
        try {
            $this->service->error(MainRespCode::AppVersionOutdated);
        } catch (ErrorResponse $e) {
            // Test HTTP status code
            $this->assertEquals(426, $e->getHttpCode());
            $this->assertEquals(426, $e->getCode());
            
            // Debug: Check if ErrorResponse has httpCode property
            $reflection = new \ReflectionClass($e);
            $httpCodeProperty = $reflection->getProperty('httpCode');
            $httpCodeProperty->setAccessible(true);
            $httpCodeValue = $httpCodeProperty->getValue($e);
            
            $this->assertEquals(426, $httpCodeValue);
            
            // Debug: Test errorResponse method
            $response = $this->service->errorResponse($e);
            $this->assertEquals(426, $response->getStatusCode());
            
            throw $e;
        }
    }

    public function test_error_throws_exception_with_string()
    {
        $this->expectException(ErrorResponse::class);
        $this->expectExceptionMessage('Test error message');
        
        $this->service->error('Test error message', 1001);
    }

    public function test_error_if_throws_when_condition_is_true()
    {
        $this->expectException(ErrorResponse::class);
        
        $this->service->errorIf(true, 'Condition is true', 1002);
    }

    public function test_error_if_does_not_throw_when_condition_is_false()
    {
        $this->service->errorIf(false, 'Condition is false', 1003);
        $this->assertTrue(true); // If we reach here, no exception was thrown
    }

    public function test_error_unless_throws_when_condition_is_false()
    {
        $this->expectException(ErrorResponse::class);
        
        $this->service->errorUnless(false, 'Condition is false', 1004);
    }

    public function test_error_unless_does_not_throw_when_condition_is_true()
    {
        $this->service->errorUnless(true, 'Condition is true', 1005);
        $this->assertTrue(true); // If we reach here, no exception was thrown
    }

    public function test_is_prod_returns_true_in_production()
    {
        Config::set('simple-exception.environment', 'production');
        $this->assertTrue($this->service->isProd());
    }

    public function test_is_prod_returns_false_in_non_production()
    {
        Config::set('simple-exception.environment', 'testing');
        $this->assertFalse($this->service->isProd());
    }

    public function test_is_dev_returns_opposite_of_is_prod()
    {
        Config::set('simple-exception.environment', 'testing');
        $this->assertTrue($this->service->isDev());
        $this->assertFalse($this->service->isProd());
    }

    public function test_build_response_creates_correct_structure()
    {
        $response = $this->service->buildResponse('Test message', 1001, ['key' => 'value']);
        
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('meta', $response);
        
        $this->assertFalse($response['success']);
        $this->assertNull($response['data']);
        $this->assertEquals('Test message', $response['error']['message']);
        $this->assertEquals(1001, $response['error']['code']);
        $this->assertEquals(['key' => 'value'], $response['meta']);
    }

    public function test_get_last_five_trace_entries()
    {
        $exception = new \Exception('Test exception');
        $trace = $this->service->getLastFiveTraceEntries($exception);
        
        $this->assertIsString($trace);
        $this->assertStringContainsString('#0', $trace);
    }
}