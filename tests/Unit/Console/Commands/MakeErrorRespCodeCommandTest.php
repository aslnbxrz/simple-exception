<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Console\Commands;

use Aslnbxrz\SimpleException\Console\Commands\MakeErrorRespCodeCommand;
use Aslnbxrz\SimpleException\Tests\TestCase;
use Illuminate\Support\Facades\File;

class MakeErrorRespCodeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clean up any existing test files
        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    private function cleanupTestFiles(): void
    {
        $testFiles = [
            app_path('Enums/TestErrorRespCode.php'),
            app_path('Enums/AnotherTestCode.php'),
        ];

        foreach ($testFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }

    public function test_command_creates_error_resp_code_enum()
    {
        $this->artisan('make:error-resp-code', ['name' => 'TestErrorRespCode'])
            ->expectsOutput('Error response code enum TestErrorRespCode created successfully!')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(app_path('Enums/TestErrorRespCode.php')));
    }

    public function test_command_prevents_duplicate_creation()
    {
        // Create first enum
        $this->artisan('make:error-resp-code', ['name' => 'AnotherTestCode'])
            ->assertExitCode(0);

        // Try to create duplicate
        $this->artisan('make:error-resp-code', ['name' => 'AnotherTestCode'])
            ->expectsOutput('Error response code enum AnotherTestCode already exists!')
            ->assertExitCode(1);
    }

    public function test_created_enum_has_correct_structure()
    {
        $this->artisan('make:error-resp-code', ['name' => 'TestErrorRespCode'])
            ->assertExitCode(0);

        $content = File::get(app_path('Enums/TestErrorRespCode.php'));

        $this->assertStringContainsString('namespace App\Enums;', $content);
        $this->assertStringContainsString('enum TestErrorRespCode', $content);
        $this->assertStringContainsString('implements ThrowableEnum', $content);
        $this->assertStringContainsString('case InvalidUsername = 2001;', $content);
        $this->assertStringContainsString('public function message(): string', $content);
        $this->assertStringContainsString('public function statusCode(): int', $content);
        $this->assertStringContainsString('public function httpStatusCode(): int', $content);
    }

    public function test_created_enum_has_usage_examples()
    {
        $this->artisan('make:error-resp-code', ['name' => 'TestErrorRespCode'])
            ->expectsOutput('Usage examples:')
            ->expectsOutput('error_if(true, TestErrorRespCode::SomeError);')
            ->expectsOutput('error_unless(false, TestErrorRespCode::AnotherError);')
            ->expectsOutput('error(TestErrorRespCode::CustomError);')
            ->assertExitCode(0);
    }
}