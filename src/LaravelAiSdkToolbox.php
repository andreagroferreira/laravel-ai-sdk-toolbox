<?php

declare(strict_types=1);

namespace AndreAgroFerreira\LaravelAiSdkToolbox;

final class LaravelAiSdkToolbox
{
    public const string VERSION = '0.1.0';

    public function version(): string
    {
        return self::VERSION;
    }
}
