<?php

namespace Aslnbxrz\SimpleException\Support;

use Aslnbxrz\SimpleException\Contracts\ThrowableEnum;
use Aslnbxrz\SimpleException\Contracts\TranslatorDriver;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class ExceptionTranslatorManager
{
    public function __construct(
        protected Container $app,
        /** @var array<string, TranslatorDriver> */
        protected array $drivers = [],
        protected ?TranslatorDriver $resolved = null,
    ) {}

    public function extend(string $name, TranslatorDriver $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function driver(): TranslatorDriver
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        $name = (string) config('simple-exception.translations.driver', 'custom');

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Translator driver [{$name}] is not registered.");
        }

        return $this->resolved = $this->drivers[$name];
    }

    public function translate(ThrowableEnum $enum, ?string $locale = null): string
    {
        return $this->driver()->translate($enum, $locale);
    }
}