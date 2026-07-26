<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string version()
 *
 * @see \AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox
 */
final class AiSdkToolbox extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox::class;
    }
}
