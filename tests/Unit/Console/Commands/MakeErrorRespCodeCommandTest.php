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

        config()->set('simple-exception.locales', ['en']);
        config()->set('simple-exception.messages', [
            'locale_fallback' => 'en',
            'patterns' => [
                'en' => ':readable error occurred.',
            ],
            'overrides' => [],
        ]);

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
            lang_path('vendor/simple-exception/test/en.php'),
            lang_path('vendor/simple-exception/user/en.php'),
            lang_path('vendor/simple-exception/another/en.php'),
        ];
        foreach ($paths as $p) {
            if (File::exists($p)) {
                File::delete($p);
            }
        }
    }

    /**
     * @throws FileNotFoundException
     */
    public function test_creates_enum_with_name_interactively()
    {
        $this->artisan('make:resp-code')
            ->expectsQuestion('Name', 'Test')
            ->expectsQuestion('Case Name (CamelCase, e.g. UserNotFound) [empty to finish]', 'UserNotFound')
            ->expectsQuestion('Code for UserNotFound (integer, e.g. 3000)', '3001')
            ->expectsConfirmation('Add another case?', false)
            ->assertExitCode(0);

        $enumFile = app_path('Enums/RespCodes/TestRespCode.php');
        $this->assertTrue(File::exists($enumFile), 'Enum file not created');

        $content = File::get($enumFile);
        $this->assertStringContainsString('namespace App\\Enums\\RespCodes;', $content);
        $this->assertStringContainsString('enum TestRespCode', $content);
        $this->assertStringContainsString('implements ThrowableEnum', $content);
        $this->assertStringContainsString('case UserNotFound = 3001;', $content);

        $langFile = lang_path('vendor/simple-exception/test/en.php');
        $this->assertTrue(File::exists($langFile), 'Lang file not created');
        $lang = include $langFile;
        $this->assertIsArray($lang);
        $this->assertArrayHasKey('user_not_found', $lang);
        $this->assertIsString($lang['user_not_found']);
    }

    /**
     * @throws FileNotFoundException
     */
    public function test_respects_config_directory_and_namespace()
    {
        Config::set('simple-exception.enum_generation.resp_codes_dir', 'Custom/ErrorCodes');

        $this->artisan('make:resp-code', [
            'name' => 'Test',
            '--cases' => 'Foo=3000',
            '--locale' => 'en',
            '--force' => true,
        ])->assertExitCode(0);

        $enumFile = app_path('Custom/ErrorCodes/TestRespCode.php');
        $this->assertTrue(File::exists($enumFile), 'Enum file not created in custom path');

        $content = File::get($enumFile);
        $this->assertStringContainsString('namespace App\\Custom\\ErrorCodes;', $content);
        $this->assertStringContainsString('case Foo = 3000;', $content);
    }

    public function test_auto_adds_and_normalizes_resp_code_suffix()
    {
        $this->artisan('make:resp-code', [
            'name' => 'User',
            '--cases' => 'A=3000',
            '--locale' => 'en',
            '--force' => true,
        ])->assertExitCode(0);
        $this->assertTrue(File::exists(app_path('Enums/RespCodes/UserRespCode.php')));

        $this->artisan('make:resp-code', [
            'name' => 'TestRespCode',
            '--cases' => 'B=3001',
            '--locale' => 'en',
            '--force' => true,
        ])->assertExitCode(0);
        $this->assertTrue(File::exists(app_path('Enums/RespCodes/TestRespCode.php')));
    }

    public function test_duplicate_creation_without_force_keeps_existing()
    {
        $this->artisan('make:resp-code', [
            'name' => 'Another',
            '--cases' => 'X=3999',
            '--locale' => 'en',
            '--force' => true,
        ])->assertExitCode(0);

        $path = app_path('Enums/RespCodes/AnotherRespCode.php');
        $this->assertTrue(File::exists($path));
        $mtime1 = filemtime($path);

        $this->artisan('make:resp-code', [
            'name' => 'Another',
            '--cases' => 'X=3999',
            '--locale' => 'en',
        ])->assertExitCode(0);

        clearstatcache(true, $path);
        $mtime2 = filemtime($path);
        $this->assertEquals($mtime1, $mtime2, 'File was unexpectedly modified without --force');
    }
}