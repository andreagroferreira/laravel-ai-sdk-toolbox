<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions;

use InvalidArgumentException;

final class PluginNotFoundException extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self(sprintf('No plugin named [%s] is registered.', $name));
    }
}
