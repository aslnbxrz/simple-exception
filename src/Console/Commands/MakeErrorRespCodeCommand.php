<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Generate a new *RespCode enum and create per-enum translation files.
 *
 * Files:
 *   app/{resp_codes_dir}/{Class}RespCode.php
 *   lang/{base_path}/{file}/{locale}.php
 */
class MakeErrorRespCodeCommand extends Command
{
    protected $signature = 'make:resp-code 
        {name? : Base name (e.g. Main => MainRespCode)} 
        {--cases= : CSV pairs Case=Code, e.g. "NotFound=404,Forbidden=403" (":" also supported)}
        {--locale= : Locale(s), comma-separated; defaults to config("simple-exception.translations.locales")}
        {--force : Overwrite enum file if present (translations are always merged)}';

    protected $description = 'Create a new error response enum and its per-enum translation file(s)';

    public function __construct(
        private readonly Filesystem          $fs,
        private readonly EnumTranslationSync $sync
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name') ?: $this->askName();
        if (!$name) {
            return self::FAILURE;
        }

        $class = $this->formatClassName($name);                        // FooRespCode
        $pairs = $this->parseCases((string)$this->option('cases'));   // [[CaseName, code], ...]
        $locales = EnumTranslationSync::normalizeLocales((string)($this->option('locale') ?? ''));
        $force = (bool)$this->option('force');

        if (empty($pairs)) {
            $this->warn('No cases provided via --cases. Enter at least one case interactively.');
            $pairs = $this->askCasesInteractive();
            if (empty($pairs)) {
                $this->error('At least one case is required.');
                return self::FAILURE;
            }
        }

        // 1) Resolve enum directory and namespace
        $relDir = (string)config('simple-exception.enum_generation.resp_codes_dir', 'Enums/RespCodes');
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

        // 3) Per-enum translation files: lang/{base_path}/{file}/{locale}.php
        $file = EnumTranslationSync::generateFileName($class); // e.g. "auth"
        foreach ($locales as $locale) {
            $langPath = EnumTranslationSync::translationFilePath($file, $locale);
            $this->fs->ensureDirectoryExists(dirname($langPath));

            $existing = $this->sync->readLangFile($langPath);
            $updated = EnumTranslationSync::mergePairs($existing, $pairs, $locale);

            $this->fs->put($langPath, EnumTranslationSync::exportLang($updated));
            $this->line(($existing === [] ? 'Lang created: ' : 'Lang updated: ') . $langPath);
        }

        // 4) Hints
        $this->line('');
        $this->line('Usage examples:');
        $this->line("  error_if(true, {$class}::{$pairs[0][0]});");
        $this->line("  error_unless(false, {$class}::{$pairs[0][0]});");
        $this->line("  error({$class}::{$pairs[0][0]});");

        return self::SUCCESS;
    }

    /** Ask interactively for the enum base name */
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
                $this->error('Invalid name. Use only letters/numbers, starting with a letter.');
                continue;
            }
            break;
        } while (true);

        return $name;
    }

    /** Interactive case builder (at least one) */
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

    /** "Foo" → "FooRespCode" (idempotent) */
    private function formatClassName(string $base): string
    {
        $base = preg_replace('/RespCode$/i', '', $base);
        return ucfirst($base) . 'RespCode';
    }

    /** Parse "Case=Code,Other:123" → [[case, code], ...] */
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

    /** Numeric code → Symfony Response constant name */
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
        $casesBlock = implode("", array_map(
            fn($p) => sprintf("    case %s = %d;\n", $p[0], $p[1]),
            $pairs
        ));

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
}