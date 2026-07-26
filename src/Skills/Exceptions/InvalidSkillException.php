<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions;

use InvalidArgumentException;

final class InvalidSkillException extends InvalidArgumentException
{
    public static function missingFrontmatter(string $path): self
    {
        return new self(sprintf('The skill file [%s] has no YAML frontmatter block.', $path));
    }

    public static function invalidFrontmatter(string $path, string $reason): self
    {
        return new self(sprintf('The skill file [%s] has invalid frontmatter: %s', $path, $reason));
    }

    public static function missingField(string $path, string $field): self
    {
        return new self(sprintf('The skill file [%s] is missing the required frontmatter field [%s].', $path, $field));
    }

    public static function nameMismatch(string $path, string $name, string $directory): self
    {
        return new self(sprintf(
            'The skill file [%s] declares the name [%s] but lives in the directory [%s]. They must match.',
            $path,
            $name,
            $directory,
        ));
    }
}
