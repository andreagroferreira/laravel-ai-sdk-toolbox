<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills;

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\InvalidSkillException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class SkillParser
{
    private const string FRONTMATTER_PATTERN = '/\A---\s*\R(.*?)\R---\s*\R?(.*)\z/s';

    public function parse(string $skillFile, string $source = 'local', Trust $trust = Trust::Trusted, bool $strictDirectory = true): Skill
    {
        $contents = @file_get_contents($skillFile);

        if ($contents === false) {
            throw InvalidSkillException::invalidFrontmatter($skillFile, 'the file could not be read');
        }

        if (preg_match(self::FRONTMATTER_PATTERN, $contents, $matches) !== 1) {
            throw InvalidSkillException::missingFrontmatter($skillFile);
        }

        [, $rawFrontmatter, $body] = $matches;

        try {
            $frontmatter = Yaml::parse($rawFrontmatter);
        } catch (ParseException $parseException) {
            throw InvalidSkillException::invalidFrontmatter($skillFile, $parseException->getMessage());
        }

        if (! is_array($frontmatter)) {
            throw InvalidSkillException::invalidFrontmatter($skillFile, 'it must be a YAML mapping');
        }

        /** @var array<string, mixed> $normalized */
        $normalized = [];

        foreach ($frontmatter as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        $frontmatter = $normalized;

        $name = $frontmatter['name'] ?? null;

        if (! is_string($name) || mb_trim($name) === '') {
            throw InvalidSkillException::missingField($skillFile, 'name');
        }

        $description = $frontmatter['description'] ?? null;

        if (! is_string($description) || mb_trim($description) === '') {
            throw InvalidSkillException::missingField($skillFile, 'description');
        }

        $directory = basename(dirname($skillFile));

        if ($strictDirectory && $name !== $directory) {
            throw InvalidSkillException::nameMismatch($skillFile, $name, $directory);
        }

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) !== 1) {
            throw InvalidSkillException::invalidFrontmatter($skillFile, 'the [name] field must be kebab-case');
        }

        $provider = $frontmatter['provider'] ?? null;

        if ($provider !== null && (! is_string($provider) || mb_trim($provider) === '')) {
            throw InvalidSkillException::invalidFrontmatter($skillFile, 'the [provider] field must be a class name');
        }

        return new Skill(
            name: $name,
            description: $description,
            instructions: mb_trim($body),
            basePath: dirname($skillFile),
            source: $source,
            trust: $trust,
            provider: $provider,
            scripts: $this->discoverScripts(dirname($skillFile)),
            frontmatter: $frontmatter,
        );
    }

    /**
     * @return array<int, string>
     */
    private function discoverScripts(string $basePath): array
    {
        $scriptsPath = $basePath.DIRECTORY_SEPARATOR.'scripts';

        if (! is_dir($scriptsPath)) {
            return [];
        }

        $scripts = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scriptsPath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $scripts[] = mb_ltrim(mb_substr($file->getPathname(), mb_strlen($scriptsPath)), DIRECTORY_SEPARATOR);
            }
        }

        sort($scripts);

        return $scripts;
    }
}
