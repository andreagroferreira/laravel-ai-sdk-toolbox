<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\CliTools;

use Illuminate\Support\Facades\File;

final class CliToolScanner
{
    private const string ENV_PATTERN = '/process\.env\.([A-Z_][A-Z0-9_]*)|process\.env\[\'([A-Z_][A-Z0-9_]*)\'\]|process\.env\["([A-Z_][A-Z0-9_]*)"\]/';

    /**
     * Extract every environment variable a CLI references, sorted.
     *
     * @return array<int, string>
     */
    public function requiredEnvironment(string $path): array
    {
        $contents = File::get($path);

        preg_match_all(self::ENV_PATTERN, $contents, $matches);

        $variables = array_filter([...$matches[1], ...$matches[2], ...$matches[3]], fn (string $variable): bool => $variable !== '');
        $variables = array_values(array_unique($variables));
        sort($variables);

        return $variables;
    }
}
