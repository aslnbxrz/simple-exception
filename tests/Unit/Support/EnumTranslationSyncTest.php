<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Support;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Aslnbxrz\SimpleException\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use ReflectionException;

class EnumTranslationSyncTest extends TestCase
{
    protected EnumTranslationSync $sync;
    protected Filesystem $fs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fs = new Filesystem();
        $this->sync = new EnumTranslationSync($this->fs);
    }

    protected function tearDown(): void
    {
        $paths = [
            lang_path('vendor/simple-exception/en/test_enum.php'),
            lang_path('vendor/simple-exception/uz/test_enum.php'),
            lang_path('vendor/simple-exception/en/user.php'),
            lang_path('vendor/simple-exception/uz/user.php'),
        ];
        foreach ($paths as $p) {
            if ($this->fs->exists($p)) $this->fs->delete($p);
        }
        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    public function test_generate_file_name_via_reflection()
    {
        $r = new \ReflectionClass($this->sync);
        $m = $r->getMethod('generateFileName');
        $m->setAccessible(true);

        $this->assertSame('user', $m->invoke($this->sync, 'App\\Enums\\UserRespCode'));
        $this->assertSame('main', $m->invoke($this->sync, 'Aslnbxrz\\SimpleException\\Enums\\RespCodes\\MainRespCode'));
        $this->assertSame('test_enum', $m->invoke($this->sync, 'TestEnum'));
    }

    /**
     * @throws ReflectionException
     */
    public function test_translation_file_path_via_reflection()
    {
        $r = new \ReflectionClass($this->sync);
        $m = $r->getMethod('translationFilePath');
        $m->setAccessible(true);

        $path = $m->invoke($this->sync, 'test_enum', 'en');
        $this->assertSame(lang_path('vendor/simple-exception/en/test_enum.php'), $path);
    }

    /**
     * @throws ReflectionException
     */
    public function test_default_message_via_reflection()
    {
        $r = new \ReflectionClass($this->sync);
        $m = $r->getMethod('defaultMessage');
        $m->setAccessible(true);

        $this->assertSame('User not found error occurred.', $m->invoke($this->sync, 'user_not_found'));
        $this->assertSame('Invalid credentials error occurred.', $m->invoke($this->sync, 'invalid_credentials'));
    }

    /**
     * @throws ReflectionException
     */
    public function test_export_lang_formats_php_array()
    {
        $r = new \ReflectionClass($this->sync);
        $m = $r->getMethod('exportLang');
        $m->setAccessible(true);

        $content = $m->invoke($this->sync, [
            'user_not_found' => 'User not found',
            'invalid_credentials' => 'Invalid credentials',
        ]);

        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString("return [", $content);
        $this->assertStringContainsString("'user_not_found' => 'User not found'", $content);
        $this->assertStringContainsString("'invalid_credentials' => 'Invalid credentials'", $content);
        $this->assertStringContainsString("];", $content);
    }
}