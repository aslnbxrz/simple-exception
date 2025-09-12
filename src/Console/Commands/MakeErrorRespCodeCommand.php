<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;

/**
 * Generate a new *RespCode enum and seed translation files.
 *
 * Examples:
 *  php artisan make:resp-code Main --cases="NotFound=404,Forbidden=403" --locale=en,uz
 *  php artisan make:resp-code User   # creates UserRespCode with UnknownError=2001 + translations
 */
class MakeErrorRespCodeCommand extends Command
{
    protected $signature = 'make:resp-code 
        {name? : Base name (e.g. Main => MainRespCode)} 
        {--cases= : CSV pairs Case=HTTPStatus, e.g. "NotFound=404,Forbidden=403"}
        {--locale=en : Locale(s) for initial lang files, comma-separated}
        {--force : Overwrite existing enum/lang files if present}';

    protected $description = 'Create a new error response enum and its translation file(s)';

    public function __construct(private readonly Filesystem $fs)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name') ?: $this->askName();
        if (!$name) {
            return self::FAILURE;
        }

        $class = $this->formatClassName($name);                 // FooRespCode
        $pairs = $this->parseCases((string)$this->option('cases')); // [[CaseName, httpStatus], ...]
        $locales = $this->normalizeLocales((string)$this->option('locale'));
        $force = (bool)$this->option('force');

        // 1) Resolve enum directory and namespace from config
        $relDir = (string)Config::get('simple-exception.enum_generation.resp_codes_dir', 'Enums/RespCodes');
        $enumDir = app_path(trim($relDir, '/'));
        $ns = 'App\\' . implode('\\', array_map('ucfirst', array_filter(explode('/', trim($relDir, '/')))));
        $enumPath = $enumDir . '/' . $class . '.php';

        $this->fs->ensureDirectoryExists($enumDir);

        // 2) Write enum file (respect --force)
        if ($this->fs->exists($enumPath) && !$force) {
            $this->warn("Enum already exists: {$enumPath} (use --force to overwrite)");
        } else {
            $this->fs->put($enumPath, $this->buildEnumSource($ns, $class, $pairs));
            $this->info("Enum created: {$enumPath}");
        }

        // 3) Seed translation files per-locale in: lang/vendor/simple-exception/{file}/{locale}.php
        $basePath = (string)Config::get('simple-exception.translations.base_path', 'vendor/simple-exception');
        $fileSnake = $this->toSnake(preg_replace('/RespCode$/', '', $class)); // e.g. MainRespCode -> main

        foreach ($locales as $locale) {
            $langDir = lang_path("{$basePath}/{$fileSnake}");
            $langPath = "{$langDir}/{$locale}.php";

            $this->fs->ensureDirectoryExists($langDir);

            if ($this->fs->exists($langPath) && !$force) {
                // Merge new keys into existing file (do NOT override existing keys)
                $existing = (array)include $langPath;
                $merged = $this->mergeLang($existing, $pairs, $locale);
                $this->fs->put($langPath, $this->exportLang($merged));
                $this->line("Lang updated: {$langPath}");
            } else {
                // Fresh file (or forced overwrite)
                $content = $this->initialLang($pairs, $locale);
                $this->fs->put($langPath, $this->exportLang($content));
                $this->line(($force ? 'Lang overwritten: ' : 'Lang created: ') . $langPath);
            }
        }

        // 4) Some usage hints
        $this->line('');
        $this->line('Usage examples:');
        $this->line("  error_if(true, {$class}::UnknownError);");
        $this->line("  error_unless(false, {$class}::UnknownError);");
        $this->line("  error({$class}::UnknownError);");

        return self::SUCCESS;
    }

    /** Interactive ask for enum base name */
    private function askName(): ?string
    {
        $this->line('🎯 Enter enum base name (e.g. "Main" → MainRespCode):');
        do {
            $name = trim((string)$this->ask('Name'));
            if ($name === '') {
                $this->error('Name cannot be empty.');
                return null;
            }
            if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name)) {
                $this->error('Use letters/numbers only, starting with a letter.');
                continue;
            }
            break;
        } while (true);

        return $name;
    }

    /** Turn "Foo" into "FooRespCode" (idempotent) */
    private function formatClassName(string $base): string
    {
        $base = preg_replace('/RespCode$/i', '', $base);
        return ucfirst($base) . 'RespCode';
    }

    /** Parse CSV "Case=Status" into array of [case, status] */
    private function parseCases(string $csv): array
    {
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $csv))) as $pair) {
            if (!str_contains($pair, '=')) continue;
            [$name, $val] = array_map('trim', explode('=', $pair, 2));
            if ($name === '' || $val === '' || !is_numeric($val)) continue;
            $out[] = [$name, (int)$val];
        }
        return $out;
    }

    /** Minimal HTTP status -> Symfony Response constant mapping */
    private function httpConst(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            406 => 'NOT_ACCEPTABLE',
            409 => 'CONFLICT',
            410 => 'GONE',
            412 => 'PRECONDITION_FAILED',
            413 => 'PAYLOAD_TOO_LARGE',
            415 => 'UNSUPPORTED_MEDIA_TYPE',
            422 => 'UNPROCESSABLE_ENTITY',
            426 => 'UPGRADE_REQUIRED',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_SERVER_ERROR',
            502 => 'BAD_GATEWAY',
            503 => 'SERVICE_UNAVAILABLE',
            504 => 'GATEWAY_TIMEOUT',
            default => 'INTERNAL_SERVER_ERROR',
        };
    }

    /** Generate enum source code */
    private function buildEnumSource(string $ns, string $class, array $pairs): string
    {
        $casesBlock = empty($pairs)
            ? "    case UnknownError = 2001;\n"
            : implode("", array_map(fn($p) => "    case {$p[0]} = {$p[1]};\n", $pairs));

        $matches = empty($pairs)
            ? "            self::UnknownError => Response::HTTP_INTERNAL_SERVER_ERROR,"
            : implode("\n", array_map(function ($p) {
                [$name, $val] = $p;
                $http = $this->httpConst($val);
                return "            self::{$name} => Response::HTTP_{$http},";
            }, $pairs));

        return <<<PHP
<?php

namespace {$ns};

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Traits\HasRespCodeTranslation;
use Aslnbxrz\SimpleException\Traits\HasStatusCode;
use Symfony\Component\HttpFoundation\Response;

enum {$class}: int implements ThrowableEnum
{
    use HasRespCodeTranslation, HasStatusCode;

{$casesBlock}
    public function httpStatusCode(): int
    {
        return match (\$this) {
{$matches}
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
PHP;
    }

    /** Normalize comma-separated locales */
    private function normalizeLocales(string $csv): array
    {
        $arr = array_values(array_unique(array_filter(array_map(
            fn($s) => strtolower(trim($s)), explode(',', $csv)
        ))));
        return $arr ?: ['en'];
    }

    /** CamelCase → snake_case */
    private function toSnake(string $s): string
    {
        $s = preg_replace('/(?<!^)[A-Z]/', '_$0', $s);
        $s = strtolower($s);
        return str_replace('__', '_', $s);
    }

    /** Default human-readable message for a snake key in a given locale */
    private function defaultMessage(string $snakeKey, string $locale): string
    {
        $readable = ucfirst(str_replace('_', ' ', $snakeKey));
        return match ($locale) {
            'uz' => "{$readable} xatosi yuz berdi.",
            'ru' => "Произошла ошибка: {$readable}.",
            default => "{$readable} error occurred.",
        };
    }

    /**
     * Create initial lang array for a given locale:
     * - If no explicit cases: add "unknown_error"
     * - Else: add one key per case (snake-cased), with a sensible default message
     */
    private function initialLang(array $pairs, string $locale): array
    {
        $lang = [];
        if (empty($pairs)) {
            $lang['unknown_error'] = $this->defaultMessage('unknown_error', $locale);
        } else {
            foreach ($pairs as [$case, $_]) {
                $key = $this->toSnake($case);
                $lang[$key] = $this->defaultMessage($key, $locale);
            }
        }
        return $lang;
    }

    /**
     * Merge new keys into existing lang array without overriding existing values.
     */
    private function mergeLang(array $existing, array $pairs, string $locale): array
    {
        $add = $this->initialLang($pairs, $locale);
        foreach ($add as $k => $v) {
            if (!array_key_exists($k, $existing)) {
                $existing[$k] = $v;
            }
        }
        return $existing;
    }

    /**
     * Export lang array as valid PHP.
     * We intentionally use var_export() to guarantee correct PHP syntax:
     *
     *   <?php
     *
     *   return array (
     *     'key' => 'value',
     *   );
     */
    private function exportLang(array $translations): string
    {
        return "<?php\n\nreturn " . var_export($translations, true) . ";\n";
    }

    /**
     * (Optional) Generate a minimal example content block.
     * Not used by default (we build content from parsed cases), but kept
     * as a stub generator if you want to seed a richer template.
     */
    private function generateLangContent(string $className, string $locale = 'en'): string
    {
        $messages = match ($locale) {
            'uz' => [
                'unknown_error' => "Noma'lum xatolik yuz berdi",
                'not_found' => 'Resurs topilmadi',
                'validation_error' => "Ma\'lumotlar noto\'g\'ri",
                'access_denied' => 'Kirish rad etildi',
            ],
            'ru' => [
                'unknown_error' => 'Произошла неизвестная ошибка',
                'not_found' => 'Ресурс не найден',
                'validation_error' => 'Данные неверны',
                'access_denied' => 'Доступ запрещен',
            ],
            default => [
                'unknown_error' => 'An unknown error occurred',
                'not_found' => 'Resource not found',
                'validation_error' => 'Validation failed',
                'access_denied' => 'Access denied',
            ],
        };

        // If you ever prefer a fixed stub instead of var_export(),
        // you can return this content. Currently unused.
        $content = [
            'unknown_error' => $messages['unknown_error'],
        ];

        return "<?php\n\nreturn " . var_export($content, true) . ";\n";
    }
}