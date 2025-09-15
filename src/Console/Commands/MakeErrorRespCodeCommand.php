<?php

namespace Aslnbxrz\SimpleException\Console\Commands;

use Aslnbxrz\SimpleException\Support\EnumTranslationSync;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Artisan command: generate a new *RespCode enum and seed translation entries.
 *
 * Behavior:
 * - Creates App\...\{Name}RespCode enum file from the configured directory/namespace.
 * - Writes translations to lang/{base_path}/{locale}.php (base_path from config).
 * - Inside each locale file, entries are grouped by enum snake name.
 * - Existing translations are preserved; only missing keys are added.
 *
 * Examples:
 *  php artisan make:resp-code Main --cases="NotFound=404,Forbidden=403" --locale=en,uz
 *  php artisan make:resp-code User
 */
class MakeErrorRespCodeCommand extends Command
{
    protected $signature = 'make:resp-code 
        {name? : Base name (e.g. Main => MainRespCode)} 
        {--cases= : CSV pairs Case=Code, e.g. "NotFound=404,Forbidden=403" (":" also supported)}
        {--locale= : Locale(s), comma-separated; defaults to config("simple-exception.translations.locales")}
        {--force : Overwrite enum file if present (translations are always merged)}';

    protected $description = 'Create a new error response enum and seed translation entries';

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

        $class = $this->formatClassName($name);                      // Example: FooRespCode
        $pairs = $this->parseCases((string)$this->option('cases')); // [[CaseName, code], ...]
        $locales = EnumTranslationSync::normalizeLocales((string)($this->option('locale') ?? ''));
        $force = (bool)$this->option('force');

        // Require at least one case. If --cases is empty, switch to interactive input.
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
            $this->warn("Enum already exists: $enumPath (use --force to overwrite)");
        } else {
            $this->fs->put($enumPath, $this->buildEnumSource($ns, $class, $pairs));
            $this->info("Enum created: $enumPath");
        }

        // 3) Generate translations into lang/{base_path}/{locale}.php
        $group = EnumTranslationSync::toSnake(preg_replace('/RespCode$/', '', $class));
        foreach ($locales as $locale) {
            $langPath = EnumTranslationSync::localeFilePath($locale);
            $this->fs->ensureDirectoryExists(dirname($langPath));

            $existing = $this->sync->readLocaleFile($langPath);
            $updated = EnumTranslationSync::mergePairsIntoGroup($existing, $group, $pairs, $locale);

            $this->fs->put($langPath, EnumTranslationSync::exportLang($updated));
            $this->line(($existing === [] ? 'Lang created: ' : 'Lang updated: ') . $langPath);
        }

        // 4) Hints
        $this->line('');
        $this->line('Usage examples:');
        $this->line("  error_if(true, $class::{$pairs[0][0]});");
        $this->line("  error_unless(false, $class::{$pairs[0][0]});");
        $this->line("  error($class::{$pairs[0][0]});");

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

    /**
     * Interactive case builder.
     * Prompts the user until at least one case is defined.
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

            $codeInput = trim((string)$this->ask("Code for $name (integer, e.g. 3000)"));
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

    /** Format class name into FooRespCode (idempotent) */
    private function formatClassName(string $base): string
    {
        $base = preg_replace('/RespCode$/i', '', $base);
        return ucfirst($base) . 'RespCode';
    }

    /** Parse CSV string like "Case=Code,Other:123" into [case, code] pairs */
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

    /** Map numeric code to Symfony\HttpFoundation\Response constant */
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
            return "            self::$name => Response::HTTP_$http,";
        }, $pairs));

        return <<<PHP
<?php

namespace $ns;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Traits\HasRespCodeTranslation;
use Aslnbxrz\SimpleException\Traits\HasStatusCode;
use Symfony\Component\HttpFoundation\Response;

enum $class: int implements ThrowableEnum
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