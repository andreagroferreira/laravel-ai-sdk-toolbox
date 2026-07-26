<?php

declare(strict_types=1);

namespace AndreAgroFerreira\LaravelAiSdkToolbox\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string version()
 *
 * @see \AndreAgroFerreira\LaravelAiSdkToolbox\LaravelAiSdkToolbox
 */
final class LaravelAiSdkToolbox extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AndreAgroFerreira\LaravelAiSdkToolbox\LaravelAiSdkToolbox::class;
    }
}
