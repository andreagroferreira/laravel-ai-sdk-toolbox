<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions;

use InvalidArgumentException;

final class SkillNotFoundException extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self(sprintf('No skill named [%s] is registered.', $name));
    }
}
