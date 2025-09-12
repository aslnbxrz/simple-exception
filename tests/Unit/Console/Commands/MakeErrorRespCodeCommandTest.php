<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Console\Commands;

use Aslnbxrz\SimpleException\Tests\TestCase;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class MakeErrorRespCodeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $paths = [
            app_path('Enums/RespCodes/TestRespCode.php'),
            app_path('Enums/RespCodes/AnotherRespCode.php'),
            app_path('Enums/RespCodes/UserRespCode.php'),
            app_path('CustomEnums/TestRespCode.php'),
            app_path('Custom/ErrorCodes/TestRespCode.php'),
            lang_path('vendor/simple-exception/en/test.php'),
            lang_path('vendor/simple-exception/en/user.php'),
        ];
        foreach ($paths as $p) {
            if (File::exists($p)) File::delete($p);
        }
    }

    /**
     * @throws FileNotFoundException
     */
    public function test_creates_enum_with_name()
    {
        $this->artisan('make:resp-code', ['name' => 'Test'])->assertExitCode(0);

        $enumFile = app_path('Enums/RespCodes/TestRespCode.php');
        $this->assertTrue(File::exists($enumFile));

        $content = File::get($enumFile);
        $this->assertStringContainsString('namespace App\\Enums\\RespCodes;', $content);
        $this->assertStringContainsString('enum TestRespCode', $content);
        $this->assertStringContainsString('implements ThrowableEnum', $content);
    }

    /**
     * @throws FileNotFoundException
     */
    public function test_respects_config_directory_and_namespace()
    {
        Config::set('simple-exception.enum_generation.resp_codes_dir', 'Custom/ErrorCodes');

        $this->artisan('make:resp-code', ['name' => 'Test'])->assertExitCode(0);

        $enumFile = app_path('Custom/ErrorCodes/TestRespCode.php');
        $this->assertTrue(File::exists($enumFile));

        $content = File::get($enumFile);
        $this->assertStringContainsString('namespace App\\Custom\\ErrorCodes;', $content);
    }

    public function test_auto_adds_and_normalizes_resp_code_suffix()
    {
        $this->artisan('make:resp-code', ['name' => 'User'])->assertExitCode(0);
        $this->assertTrue(File::exists(app_path('Enums/RespCodes/UserRespCode.php')));

        $this->artisan('make:resp-code', ['name' => 'TestRespCode'])->assertExitCode(0);
        $this->assertTrue(File::exists(app_path('Enums/RespCodes/TestRespCode.php')));
    }

    public function test_duplicate_creation_without_force_keeps_existing()
    {
        $this->artisan('make:resp-code', ['name' => 'Another'])->assertExitCode(0);
        $mtime1 = filemtime(app_path('Enums/RespCodes/AnotherRespCode.php'));

        // Second time should not overwrite without --force
        $this->artisan('make:resp-code', ['name' => 'Another'])->assertExitCode(0);
        $mtime2 = filemtime(app_path('Enums/RespCodes/AnotherRespCode.php'));

        $this->assertEquals($mtime1, $mtime2);
    }
}