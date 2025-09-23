<?php

namespace Aslnbxrz\SimpleException\Enums;

use Aslnbxrz\SimpleException\Contracts\TranslatorDriver;
use Aslnbxrz\SimpleException\Translations\Drivers\CustomFileDriver;
use Aslnbxrz\SimpleException\Translations\Drivers\SimpleTranslationDriver;

enum TranslationDriver: string
{
    case Custom = 'custom';
    case SimpleTranslation = 'simple-translation';

    public function make(): TranslatorDriver
    {
        return match ($this) {
            self::SimpleTranslation => new SimpleTranslationDriver(),
            self::Custom => new CustomFileDriver(),
        };
    }
}
