<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Console\Commands;

use Aslnbxrz\SimpleException\Console\Commands\MakeErrorRespCodeCommand;
use Aslnbxrz\SimpleException\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

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
            app_path('Enums/RespCodes/TestRespCode.php'),
            app_path('Enums/RespCodes/AnotherRespCode.php'),
            app_path('Enums/RespCodes/UserRespCode.php'),
            app_path('CustomEnums/TestRespCode.php'),
            app_path('CustomEnums/AnotherRespCode.php'),
            app_path('Custom/ErrorCodes/TestRespCode.php'),
        ];

        foreach ($testFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }

    public function test_command_creates_error_resp_code_enum_with_name()
    {
        $this->artisan('make:resp-code', ['name' => 'Test'])
            ->expectsOutput('✅ Error response code enum TestRespCode created successfully!')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(app_path('Enums/RespCodes/TestRespCode.php')));
    }

    public function test_command_uses_config_directory()
    {
        // Set custom directory in config
        Config::set('simple-exception.enum_generation.resp_codes_dir', 'CustomEnums');
        
        $this->artisan('make:resp-code', ['name' => 'Test'])
            ->expectsOutput('✅ Error response code enum TestRespCode created successfully!')
            ->expectsOutput('📦 Namespace: App\\CustomEnums')
            ->expectsOutput('⚙️  Config: Directory set to \'CustomEnums\' in simple-exception config')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(app_path('CustomEnums/TestRespCode.php')));
    }

    public function test_command_generates_namespace_from_directory()
    {
        // Set custom directory in config
        Config::set('simple-exception.enum_generation.resp_codes_dir', 'Custom/ErrorCodes');
        
        $this->artisan('make:resp-code', ['name' => 'Test'])
            ->expectsOutput('📦 Namespace: App\\Custom\\ErrorCodes')
            ->assertExitCode(0);

        $content = File::get(app_path('Custom/ErrorCodes/TestRespCode.php'));
        $this->assertStringContainsString('namespace App\\Custom\\ErrorCodes;', $content);
    }

    public function test_command_automatically_adds_resp_code_suffix()
    {
        $this->artisan('make:resp-code', ['name' => 'User'])
            ->expectsOutput('✅ Error response code enum UserRespCode created successfully!')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(app_path('Enums/RespCodes/UserRespCode.php')));
    }

    public function test_command_removes_existing_resp_code_suffix()
    {
        $this->artisan('make:resp-code', ['name' => 'TestRespCode'])
            ->expectsOutput('✅ Error response code enum TestRespCode created successfully!')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(app_path('Enums/RespCodes/TestRespCode.php')));
    }

    public function test_command_prevents_duplicate_creation()
    {
        // Create first enum
        $this->artisan('make:resp-code', ['name' => 'Another'])
            ->assertExitCode(0);

        // Try to create duplicate
        $this->artisan('make:resp-code', ['name' => 'Another'])
            ->expectsOutput('⚠️ Error response code enum AnotherRespCode already exists at ' . app_path('Enums/RespCodes/AnotherRespCode.php') . '!')
            ->assertExitCode(1);
    }

    public function test_created_enum_has_correct_structure()
    {
        $this->artisan('make:resp-code', ['name' => 'Test'])
            ->assertExitCode(0);

        $content = File::get(app_path('Enums/RespCodes/TestRespCode.php'));

        $this->assertStringContainsString('namespace App\\Enums\\RespCodes;', $content);
        $this->assertStringContainsString('enum TestRespCode', $content);
        $this->assertStringContainsString('implements ThrowableEnum', $content);
        $this->assertStringContainsString('case UnknownError = 2001;', $content);
        $this->assertStringContainsString('public function message(): string', $content);
        $this->assertStringContainsString('public function statusCode(): int', $content);
        $this->assertStringContainsString('public function httpStatusCode(): int', $content);
    }

    public function test_created_enum_has_usage_examples()
    {
        $this->artisan('make:resp-code', ['name' => 'Test'])
            ->expectsOutput('🚀 Usage examples:')
            ->expectsOutput('   error_if(true, TestRespCode::UnknownError);')
            ->expectsOutput('   error_unless(false, TestRespCode::UnknownError);')
            ->expectsOutput('   error(TestRespCode::UnknownError);')
            ->expectsOutput('💡 Tip: You can add more cases to the enum as needed!')
            ->assertExitCode(0);
    }
}