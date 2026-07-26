<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\CliTools\Exceptions;

use InvalidArgumentException;

final class CliToolNotFoundException extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self(sprintf('No CLI tool named [%s] is registered.', $name));
    }
}
