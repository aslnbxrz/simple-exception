<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Exceptions;

use Aslnbxrz\SimpleException\Exceptions\ErrorResponse;
use Aslnbxrz\SimpleException\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ErrorResponseTest extends TestCase
{
    public function test_error_response_constructor()
    {
        $exception = new ErrorResponse('Test message', 1001, null, Response::HTTP_BAD_REQUEST);
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(1001, $exception->getCode());
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $exception->getHttpCode());
    }

    public function test_error_response_with_previous_exception()
    {
        $previous = new \Exception('Previous exception');
        $exception = new ErrorResponse('Test message', 1001, $previous, Response::HTTP_BAD_REQUEST);
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(1001, $exception->getCode());
        $this->assertEquals($previous, $exception->getPrevious());
    }

    public function test_error_response_default_http_code()
    {
        $exception = new ErrorResponse('Test message', 1001);
        
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }
}