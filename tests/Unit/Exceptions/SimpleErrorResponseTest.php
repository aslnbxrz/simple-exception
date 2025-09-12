<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Exceptions;

use Aslnbxrz\SimpleException\Exceptions\SimpleErrorResponse;
use Aslnbxrz\SimpleException\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class SimpleErrorResponseTest extends TestCase
{
    public function test_constructor_sets_values()
    {
        $exception = new SimpleErrorResponse('Test message', 1001, null, Response::HTTP_BAD_REQUEST);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(1001, $exception->getCode());
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $exception->resolvedHttpCode());
    }

    public function test_previous_exception_is_preserved()
    {
        $previous = new \Exception('Previous exception');
        $exception = new SimpleErrorResponse('Test message', 1001, $previous, Response::HTTP_BAD_REQUEST);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(1001, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_default_http_code_is_internal_server_error()
    {
        $exception = new SimpleErrorResponse('Test message', 1001);

        $this->assertNull($exception->resolvedHttpCode());
    }
}