<?php

namespace Aslnbxrz\SimpleException\Tests\Unit\Support;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Aslnbxrz\SimpleException\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

class EnumTranslationSyncTest extends TestCase
{
    protected EnumTranslationSync $sync;
    protected Filesystem $fs;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('simple-exception.translations.base_path', 'simple-exception');
        config()->set('simple-exception.translations.locales', ['en']);
        config()->set('simple-exception.translations.locale_fallback', 'en');
        config()->set('simple-exception.translations.messages', [
            'patterns' => [
                'en' => ':readable error occurred.',
            ],
        ]);

        $this->fs = new Filesystem();
        $this->sync = new EnumTranslationSync($this->fs);
    }

    protected function tearDown(): void
    {
        $paths = [
            lang_path('simple-exception/en.php'),
            lang_path('simple-exception/uz.php'),
        ];
        foreach ($paths as $p) {
            if ($this->fs->exists($p)) {
                $this->fs->delete($p);
            }
        }
        parent::tearDown();
    }

    public function test_group_name_by_enum_basename_snake()
    {
        $g1 = EnumTranslationSync::toSnake(preg_replace('/RespCode$/i', '', class_basename('App\\Enums\\UserRespCode')));
        $g2 = EnumTranslationSync::toSnake(preg_replace('/RespCode$/i', '', class_basename('Aslnbxrz\\SimpleException\\Enums\\RespCodes\\MainRespCode')));
        $g3 = EnumTranslationSync::toSnake(preg_replace('/RespCode$/i', '', class_basename('TestEnum')));

        $this->assertSame('user', $g1);
        $this->assertSame('main', $g2);
        // "TestEnum" -> "test_enum" (our toSnake inserts underscore before capitals)
        $this->assertSame('test_enum', $g3);
    }

    public function test_locale_file_path_for_en_locale()
    {
        $path = EnumTranslationSync::localeFilePath('en');
        $this->assertSame(lang_path('simple-exception/en.php'), $path);
    }

    public function test_default_message_static()
    {
        $this->assertSame('User not found error occurred.', EnumTranslationSync::defaultMessage('user_not_found', 'en'));
        $this->assertSame('Invalid credentials error occurred.', EnumTranslationSync::defaultMessage('invalid_credentials', 'en'));
    }

    public function test_export_lang_formats_php_array()
    {
        $content = EnumTranslationSync::exportLang([
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