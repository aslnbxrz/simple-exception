<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Support;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Aslnbxrz\SimpleException\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;

class EnumTranslationSyncTest extends TestCase
{
    protected EnumTranslationSync $syncService;
    protected Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->files = new Filesystem();
        $this->syncService = new EnumTranslationSync($this->files);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $testFiles = [
            lang_path('vendor/simple-exception/test_enum/en.php'),
            lang_path('vendor/simple-exception/test_enum/uz.php'),
            lang_path('vendor/simple-exception/user/en.php'),
            lang_path('vendor/simple-exception/user/uz.php'),
        ];

        foreach ($testFiles as $file) {
            if ($this->files->exists($file)) {
                $this->files->delete($file);
            }
        }

        parent::tearDown();
    }

    public function test_generates_file_name_from_enum_class()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('generateFileName');
        $method->setAccessible(true);

        $this->assertEquals('user', $method->invoke($this->syncService, 'App\\Enums\\UserRespCode'));
        $this->assertEquals('main', $method->invoke($this->syncService, 'Aslnbxrz\\SimpleException\\Enums\\RespCodes\\MainRespCode'));
        $this->assertEquals('test_enum', $method->invoke($this->syncService, 'TestEnum'));
    }

    public function test_generates_translation_file_path()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('getTranslationFilePath');
        $method->setAccessible(true);

        $path = $method->invoke($this->syncService, 'test_enum', 'en');
        $expectedPath = lang_path('vendor/simple-exception/test_enum/en.php');
        
        $this->assertEquals($expectedPath, $path);
    }

    public function test_generates_default_message_from_key()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('generateDefaultMessage');
        $method->setAccessible(true);

        $this->assertEquals('User not found error occurred.', $method->invoke($this->syncService, 'user_not_found'));
        $this->assertEquals('Invalid credentials error occurred.', $method->invoke($this->syncService, 'invalid_credentials'));
    }

    public function test_generates_translation_file_content()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('generateTranslationFileContent');
        $method->setAccessible(true);

        $translations = [
            'user_not_found' => 'User not found',
            'invalid_credentials' => 'Invalid credentials provided',
        ];

        $content = $method->invoke($this->syncService, $translations);
        
        $this->assertStringContainsString("<?php", $content);
        $this->assertStringContainsString("return [", $content);
        $this->assertStringContainsString("'user_not_found' => 'User not found',", $content);
        $this->assertStringContainsString("'invalid_credentials' => 'Invalid credentials provided',", $content);
        $this->assertStringContainsString("];", $content);
    }

    public function test_merges_translations_correctly()
    {
        $reflection = new \ReflectionClass($this->syncService);
        $method = $reflection->getMethod('mergeTranslations');
        $method->setAccessible(true);

        $existing = [
            'user_not_found' => 'User not found (existing)',
            'access_denied' => 'Access denied (existing)',
        ];

        $new = [
            'user_not_found' => 'User not found (new)',
            'invalid_credentials' => 'Invalid credentials (new)',
        ];

        $result = $method->invoke($this->syncService, $existing, $new);

        $expected = [
            'user_not_found' => 'User not found (existing)',  // Existing preserved
            'access_denied' => 'Access denied (existing)',    // Existing preserved
            'invalid_credentials' => 'Invalid credentials (new)', // New added
        ];

        $this->assertEquals($expected, $result);
    }
}
