<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions;

use InvalidArgumentException;

final class InvalidPluginManifestException extends InvalidArgumentException
{
    public static function notFound(string $path): self
    {
        return new self(sprintf('No ai-plugin.json (or .claude-plugin/plugin.json) found in [%s].', $path));
    }

    public static function invalid(string $path, string $reason): self
    {
        return new self(sprintf('The plugin manifest in [%s] is invalid: %s', $path, $reason));
    }
}
