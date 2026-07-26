<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills;

enum Trust: string
{
    case Trusted = 'trusted';
    case Untrusted = 'untrusted';

    public function isTrusted(): bool
    {
        return $this === self::Trusted;
    }
}
