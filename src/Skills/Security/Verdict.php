<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Security;

enum Verdict: string
{
    case Safe = 'safe';
    case Warnings = 'warnings';
    case Blocked = 'blocked';
}
