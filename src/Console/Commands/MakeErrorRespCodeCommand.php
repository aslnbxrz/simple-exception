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

        $class = $this->formatClassName($name);                  // FooRespCode
        $pairs = $this->parseCases((string)$this->option('cases')); // [[CaseName, code], ...]
        $locales = $this->normalizeLocales((string)$this->option('locale'));
        $force = (bool)$this->option('force');

        // ✅ Yangi: agar --cases bo'sh bo'lsa, interaktiv rejimga o'tamiz
        if (empty($pairs)) {
            $this->warn('No cases provided via --cases. Enter at least one case interactively.');
            $pairs = $this->askCasesInteractive();
            if (empty($pairs)) {
                $this->error('At least one case is required.');
                return self::FAILURE;
            }
        }

        // 1) Resolve enum directory and namespace...
        $relDir = (string)Config::get('simple-exception.enum_generation.resp_codes_dir', 'Enums/RespCodes');
        $enumDir = app_path(trim($relDir, '/'));
        $ns = 'App\\' . implode('\\', array_map('ucfirst', array_filter(explode('/', trim($relDir, '/')))));
        $enumPath = $enumDir . '/' . $class . '.php';

        $this->fs->ensureDirectoryExists($enumDir);

        // 2) Write enum file
        if ($this->fs->exists($enumPath) && !$force) {
            $this->warn("Enum already exists: {$enumPath} (use --force to overwrite)");
        } else {
            $this->fs->put($enumPath, $this->buildEnumSource($ns, $class, $pairs));
            $this->info("Enum created: {$enumPath}");
        }

        // 3) Seed translations
        $basePath = (string)Config::get('simple-exception.translations.base_path', 'vendor/simple-exception');
        $fileSnake = $this->toSnake(\preg_replace('/RespCode$/', '', $class));

        foreach ($locales as $locale) {
            $langDir = lang_path("{$basePath}/{$fileSnake}");
            $langPath = "{$langDir}/{$locale}.php";

            $this->fs->ensureDirectoryExists($langDir);

            if ($this->fs->exists($langPath) && !$force) {
                $existing = (array)include $langPath;
                $merged = $this->mergeLang($existing, $pairs, $locale);
                $this->fs->put($langPath, $this->exportLang($merged));
                $this->line("Lang updated: {$langPath}");
            } else {
                $content = $this->initialLang($pairs, $locale);
                $this->fs->put($langPath, $this->exportLang($content));
                $this->line(($force ? 'Lang overwritten: ' : 'Lang created: ') . $langPath);
            }
        }

        // 4) Hints
        $this->line('');
        $this->line('Usage examples:');
        $this->line("  error_if(true, {$class}::{$pairs[0][0]});");
        $this->line("  error_unless(false, {$class}::{$pairs[0][0]});");
        $this->line("  error({$class}::{$pairs[0][0]});");

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

    /**
     * Interaktiv tarzda kamida 1 ta case yig'adi.
     * Har bir case uchun: Name (CamelCase) va Code (butun son, masalan 3000).
     */
    private function askCasesInteractive(): array
    {
        $this->line('➕ Add cases (at least one). Leave Name empty to finish (after you have ≥1).');

        $pairs = [];
        while (true) {
            $name = trim((string)$this->ask('Case Name (CamelCase, e.g. UserNotFound) [empty to finish]'));

            if ($name === '') {
                if (!empty($pairs)) {
                    break;
                }
                $this->warn('You must provide at least one case.');
                continue;
            }

            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                $this->error('Invalid case name. Use letters/numbers/underscore, starting with a letter.');
                continue;
            }

            $codeInput = trim((string)$this->ask("Code for {$name} (integer, e.g. 3000)"));
            if ($codeInput === '' || !ctype_digit($codeInput)) {
                $this->error('Code must be a positive integer.');
                continue;
            }

            $pairs[] = [$name, (int)$codeInput];

            if (!$this->confirm('Add another case?', true)) {
                break;
            }
        }

        return $pairs;
    }

    /** Turn "Foo" into "FooRespCode" (idempotent) */
    private function formatClassName(string $base): string
    {
        $base = preg_replace('/RespCode$/i', '', $base);
        return ucfirst($base) . 'RespCode';
    }

    /** Parse CSV like "Case=Status,Other:123" into array of [case, status] */
    /** Parse CSV like "Case=Code,Other:123" into array of [case, code] */
    private function parseCases(string $csv): array
    {
        $out = [];
        if (trim($csv) === '') {
            return $out;
        }
        foreach (array_filter(array_map('trim', explode(',', $csv))) as $pair) {
            if (!preg_match('/^\s*([A-Za-z][A-Za-z0-9_]*)\s*[:=]\s*([0-9]+)\s*$/', $pair, $m)) {
                continue;
            }
            $out[] = [$m[1], (int)$m[2]];
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
    /** Generate enum source code without fallback UnknownError case */
    private function buildEnumSource(string $ns, string $class, array $pairs): string
    {
        // $pairs always ≥ 1 at this point
        $casesBlock = implode("", array_map(fn($p) => "    case {$p[0]} = {$p[1]};\n", $pairs));

        $matches = implode("\n", array_map(function ($p) {
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

    /** Normalize comma-separated locales or fall back to config('simple-exception.locales') */
    private function normalizeLocales(string $csv): array
    {
        $arr = array_values(array_unique(array_filter(array_map(
            fn($s) => strtolower(trim($s)), explode(',', $csv)
        ))));

        if (!empty($arr)) {
            return $arr;
        }

        // Fallback to config
        $cfg = (array)config('simple-exception.messages.locales', []);
        $cfg = array_values(array_unique(array_map(fn($s) => strtolower(trim($s)), $cfg)));

        return $cfg ?: ['en'];
    }

    /** CamelCase → snake_case */
    private function toSnake(string $s): string
    {
        $s = preg_replace('/(?<!^)[A-Z]/', '_$0', $s);
        $s = strtolower($s);
        return str_replace('__', '_', $s);
    }

    /** Build default message using config-driven patterns/overrides */
    private function defaultMessage(string $snakeKey, string $locale): string
    {
        $messagesCfg = (array)config('simple-exception.messages', []);
        $patterns = (array)($messagesCfg['patterns'] ?? []);
        $fallback = (string)($messagesCfg['locale_fallback'] ?? 'en');

        // Pattern
        $pattern = $patterns[$locale]
            ?? $patterns[$fallback]
            ?? ':readable error occurred.';

        // prepare :readable
        $readable = ucfirst(str_replace('_', ' ', $snakeKey));

        return str_replace(':readable', $readable, $pattern);
    }

    /** Create initial lang array for a given locale */
    private function initialLang(array $pairs, string $locale): array
    {
        $lang = [];
        foreach ($pairs as [$case, $_]) {
            $key = $this->toSnake($case);
            $lang[$key] = $this->defaultMessage($key, $locale);
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
     *   return [
     *     'key' => 'value',
     *   ];
     */
    /** Export lang array as PHP using short array syntax [ ] */
    private function exportLang(array $translations): string
    {
        ksort($translations);

        $lines = [];
        foreach ($translations as $k => $v) {
            $key = addslashes($k);
            $val = addslashes($v);
            $lines[] = "    '{$key}' => '{$val}',";
        }

        $body = implode("\n", $lines);

        return <<<PHP
<?php

return [
{$body}
];
PHP;
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