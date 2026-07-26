<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions;

use RuntimeException;

final class PluginInstallException extends RuntimeException
{
    public static function unsupportedSource(string $source): self
    {
        return new self(sprintf('The source [%s] is not supported. Use a local path, a git URL or a GitHub shorthand (vendor/repo).', $source));
    }

    public static function alreadyInstalled(string $name): self
    {
        return new self(sprintf('The plugin [%s] is already installed. Remove it first with ai:plugin-remove.', $name));
    }

    public static function cloneFailed(string $source, string $output): self
    {
        return new self(sprintf('Failed to clone [%s]: %s', $source, mb_trim($output)));
    }
}
