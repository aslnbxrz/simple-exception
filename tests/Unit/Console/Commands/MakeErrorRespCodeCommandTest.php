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
            app_path('Enums/TestRespCode.php'),
            app_path('Enums/AnotherRespCode.php'),
            app_path('Enums/UserRespCode.php'),
            app_path('CustomEnums/TestRespCode.php'),
            app_path('CustomEnums/AnotherRespCode.php'),
        ];

        foreach ($testFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }

    public function test_command_creates_error_resp_code_enum_with_name()
    {
        $this->artisan('make:error-resp-code', ['name' => 'Test'])
            ->expectsOutput('✅ Error response code enum TestRespCode created successfully!')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(app_path('Enums/TestRespCode.php')));
    }

    public function test_command_uses_config_directory()
    {
        // Set custom directory in config
        Config::set('simple-exception.enum_generation.resp_codes_dir', 'CustomEnums');
        
        $this->artisan('make:error-resp-code', ['name' => 'Test'])
            ->expectsOutput('✅ Error response code enum TestRespCode created successfully!')
            ->expectsOutput('📦 Namespace: App\\CustomEnums')
            ->expectsOutput('⚙️  Config: Directory set to \'CustomEnums\' in simple-exception config')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(app_path('CustomEnums/TestRespCode.php')));
    }

    public function test_command_uses_custom_namespace()
    {
        // Set custom namespace in config
        Config::set('simple-exception.enum_generation.namespace', 'App\\Custom\\Enums');
        Config::set('simple-exception.enum_generation.auto_namespace', false);
        
        $this->artisan('make:error-resp-code', ['name' => 'Test'])
            ->expectsOutput('📦 Namespace: App\\Custom\\Enums')
            ->assertExitCode(0);

        $content = File::get(app_path('Enums/TestRespCode.php'));
        $this->assertStringContainsString('namespace App\\Custom\\Enums;', $content);
    }

    public function test_command_automatically_adds_resp_code_suffix()
    {
        $this->artisan('make:error-resp-code', ['name' => 'User'])
            ->expectsOutput('✅ Error response code enum UserRespCode created successfully!')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(app_path('Enums/UserRespCode.php')));
    }

    public function test_command_removes_existing_resp_code_suffix()
    {
        $this->artisan('make:error-resp-code', ['name' => 'TestRespCode'])
            ->expectsOutput('✅ Error response code enum TestRespCode created successfully!')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(app_path('Enums/TestRespCode.php')));
    }

    public function test_command_prevents_duplicate_creation()
    {
        // Create first enum
        $this->artisan('make:error-resp-code', ['name' => 'Another'])
            ->assertExitCode(0);

        // Try to create duplicate
        $this->artisan('make:error-resp-code', ['name' => 'Another'])
            ->expectsOutput('Error response code enum AnotherRespCode already exists!')
            ->assertExitCode(1);
    }

    public function test_created_enum_has_correct_structure()
    {
        $this->artisan('make:error-resp-code', ['name' => 'Test'])
            ->assertExitCode(0);

        $content = File::get(app_path('Enums/TestRespCode.php'));

        $this->assertStringContainsString('namespace App\\Enums;', $content);
        $this->assertStringContainsString('enum TestRespCode', $content);
        $this->assertStringContainsString('implements ThrowableEnum', $content);
        $this->assertStringContainsString('case ExampleError = 2001;', $content);
        $this->assertStringContainsString('public function message(): string', $content);
        $this->assertStringContainsString('public function statusCode(): int', $content);
        $this->assertStringContainsString('public function httpStatusCode(): int', $content);
    }

    public function test_created_enum_has_usage_examples()
    {
        $this->artisan('make:error-resp-code', ['name' => 'Test'])
            ->expectsOutput('🚀 Usage examples:')
            ->expectsOutput('   error_if(true, TestRespCode::ExampleError);')
            ->expectsOutput('   error_unless(false, TestRespCode::ExampleError);')
            ->expectsOutput('   error(TestRespCode::ExampleError);')
            ->expectsOutput('💡 Tip: You can add more cases to the enum as needed!')
            ->assertExitCode(0);
    }
}