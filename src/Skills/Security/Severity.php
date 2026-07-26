<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Security;

enum Severity: string
{
    case Warning = 'warning';
    case Blocked = 'blocked';
}
