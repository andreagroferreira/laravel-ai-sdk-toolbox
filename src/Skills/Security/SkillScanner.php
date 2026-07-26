<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Security;

use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use FilesystemIterator;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SkillScanner
{
    /**
     * PHP functions that execute code or processes. PHP runs in-process, so
     * these block the installation.
     *
     * @var array<string, string>
     */
    private const array BLOCKED_PHP_FUNCTIONS = [
        'exec' => 'executes an external program',
        'shell_exec' => 'executes a command via the shell',
        'system' => 'executes an external program',
        'passthru' => 'executes an external program',
        'proc_open' => 'opens a process',
        'popen' => 'opens a process pipe',
        'pcntl_exec' => 'replaces the current process',
        'eval' => 'evaluates arbitrary PHP code',
        'assert' => 'may evaluate arbitrary PHP code',
    ];

    /**
     * PHP functions that are allowed but worth reviewing.
     *
     * @var array<string, string>
     */
    private const array WARNING_PHP_FUNCTIONS = [
        'base64_decode' => 'decodes base64 content (possible obfuscation)',
        'getenv' => 'reads environment variables',
        'file_put_contents' => 'writes files',
        'fwrite' => 'writes files',
        'unlink' => 'deletes files',
        'rmdir' => 'deletes directories',
        'curl_exec' => 'performs network requests',
        'file_get_contents' => 'may read files or fetch URLs',
    ];

    /**
     * Patterns looked for in script files (python, js, shell). Scripts are
     * executable by design, so everything here is a warning.
     *
     * @var array<string, string>
     */
    private const array SCRIPT_PATTERNS = [
        '/\bos\.system\s*\(/i' => 'os.system() executes shell commands',
        '/\bsubprocess\b/i' => 'subprocess spawns processes',
        '/\bchild_process\b/i' => 'child_process spawns processes',
        '/\b(os\.)?environ\b/i' => 'reads environment variables',
        '/\bprocess\.env\b/i' => 'reads environment variables',
        '/\b(base64|b64decode|b64encode)\b/i' => 'base64 usage (possible obfuscation)',
        '/\b(curl|wget|requests\.|urllib|fetch\s*\(|axios)\b/i' => 'performs network requests',
        '/\b(__import__|eval\s*\(|exec\s*\(|Function\s*\()/i' => 'evaluates arbitrary code',
    ];

    /**
     * Prompt-injection heuristics for the skill markdown.
     *
     * @var array<string, string>
     */
    private const array MARKDOWN_PATTERNS = [
        '/ignore (all |any |the )?(previous|prior|above) instructions/i' => 'attempts to override prior instructions',
        '/disregard (all |any |the )?(previous|prior|above)/i' => 'attempts to override prior instructions',
        '/\.env\b/i' => 'references the .env file',
        '/\b(api[_ -]?key|secret|password|credential|token)\b/i' => 'references credentials or secrets',
        '/\bexfiltrat/i' => 'references data exfiltration',
        '/\b(send|post|upload|forward).{0,40}\bto\b.{0,40}https?:\/\//i' => 'instructs sending data to a URL',
    ];

    public function scan(Skill $skill): ScanReport
    {
        $findings = new Collection;

        foreach ($this->files($skill->basePath) as $file) {
            $relative = $this->relativePath($skill->basePath, $file);
            $extension = mb_strtolower(pathinfo($file, PATHINFO_EXTENSION));

            $findings = $findings->merge(match ($extension) {
                'php' => $this->scanPhp($file, $relative),
                'py', 'js', 'mjs', 'sh' => $this->scanScript($file, $relative),
                default => [],
            });
        }

        return new ScanReport($skill, $findings->merge($this->scanMarkdown($skill)));
    }

    /**
     * @return array<int, string>
     */
    private function files(string $basePath): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<int, Finding>
     */
    private function scanPhp(string $file, string $relative): array
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            return [];
        }

        $findings = [];
        $tokens = token_get_all($contents);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            $function = mb_strtolower($token[1]);
            $rule = null;
            $severity = null;

            if (isset(self::BLOCKED_PHP_FUNCTIONS[$function])) {
                $rule = self::BLOCKED_PHP_FUNCTIONS[$function];
                $severity = Severity::Blocked;
            } elseif (isset(self::WARNING_PHP_FUNCTIONS[$function])) {
                $rule = self::WARNING_PHP_FUNCTIONS[$function];
                $severity = Severity::Warning;
            }

            if ($rule === null || ! $this->isFunctionCall($tokens, $i)) {
                continue;
            }

            $findings[] = new Finding(
                rule: 'php.'.$function,
                severity: $severity,
                file: $relative,
                message: sprintf('The function [%s] %s.', $function, $rule),
                line: $token[2],
            );
        }

        return $findings;
    }

    /**
     * @param  array<int, array{int, string, int}|string>  $tokens
     */
    private function isFunctionCall(array $tokens, int $index): bool
    {
        $count = count($tokens);

        for ($i = $index + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token === '(';
        }

        return false;
    }

    /**
     * @return array<int, Finding>
     */
    private function scanScript(string $file, string $relative): array
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            return [];
        }

        $findings = [];

        foreach (self::SCRIPT_PATTERNS as $pattern => $reason) {
            if (preg_match($pattern, $contents) === 1) {
                $findings[] = new Finding(
                    rule: 'script.'.$relative,
                    severity: Severity::Warning,
                    file: $relative,
                    message: sprintf('The script %s.', $reason),
                );
            }
        }

        return $findings;
    }

    /**
     * @return array<int, Finding>
     */
    private function scanMarkdown(Skill $skill): array
    {
        $findings = [];

        foreach (self::MARKDOWN_PATTERNS as $pattern => $reason) {
            if (preg_match($pattern, $skill->instructions) === 1) {
                $findings[] = new Finding(
                    rule: 'markdown.injection',
                    severity: Severity::Warning,
                    file: 'SKILL.md',
                    message: sprintf('The skill instructions %s.', $reason),
                );
            }
        }

        return $findings;
    }

    private function relativePath(string $basePath, string $file): string
    {
        return mb_ltrim(mb_substr($file, mb_strlen($basePath)), DIRECTORY_SEPARATOR);
    }
}
