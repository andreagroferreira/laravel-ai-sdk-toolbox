<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;
use AndreAgroFerreira\AiSdkToolbox\CliTools\CliToolScanner;
use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\InvalidSkillException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillInstallException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\ScanReport;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillScanner;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\Verdict;
use FilesystemIterator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SkillInstaller
{
    public function __construct(
        private readonly SkillParser $parser,
        private readonly SkillScanner $scanner,
        private readonly Repository $config,
        private readonly SkillLock $lock,
        private readonly CliToolScanner $cliScanner,
    ) {}

    /**
     * Expand GitHub shorthands (vendor/repo) into full git URLs.
     */
    public static function normalizeSource(string $source): string
    {
        if (preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $source) === 1) {
            return sprintf('https://github.com/%s.git', $source);
        }

        return $source;
    }

    /**
     * Install a single skill from a local path, a git URL or a GitHub shorthand.
     *
     * @param  callable(ScanReport): bool|null  $confirm
     *
     * @throws SkillInstallException
     */
    public function install(string $source, ?string $path = null, bool $force = false, ?callable $confirm = null): InstallResult
    {
        [$root, $version, $cleanup] = $this->resolveSourceRoot($source);

        try {
            $directory = $this->resolveSkillDirectory($root, $path);
            $result = $this->installFromDirectory($directory, $source, $version, $force, $confirm, strictDirectory: $directory !== $root);

            return $result->withCliTools($this->installCliTools($root, $source, $version));
        } finally {
            $cleanup();
        }
    }

    /**
     * Install every skill found in the source (recursive SKILL.md discovery).
     *
     * @param  callable(ScanReport): bool|null  $confirm
     */
    public function installMany(string $source, bool $force = false, ?callable $confirm = null): InstallManyResult
    {
        [$root, $version, $cleanup] = $this->resolveSourceRoot($source);

        try {
            $directories = $this->discoverSkillDirectories($root);
            $installed = [];
            $skipped = [];
            $failed = [];

            foreach ($directories as $directory) {
                try {
                    $installed[] = $this->installFromDirectory($directory, $source, $version, $force, $confirm, strictDirectory: $directory !== $root);
                } catch (SkillInstallException $skillInstallException) {
                    if ($skillInstallException->reason === 'already-installed') {
                        $skipped[] = basename($directory);
                    } else {
                        $failed[basename($directory)] = $skillInstallException->getMessage();
                    }
                } catch (InvalidSkillException $invalidSkillException) {
                    $failed[basename($directory)] = $invalidSkillException->getMessage();
                }
            }

            return new InstallManyResult($installed, $skipped, $failed, $this->installCliTools($root, $source, $version));
        } finally {
            $cleanup();
        }
    }

    /**
     * Remove a skill from the "installed" path and from the lock file.
     */
    public function uninstall(string $name): void
    {
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) !== 1) {
            throw SkillInstallException::unsupportedSource($name);
        }

        $destination = $this->installedPath().DIRECTORY_SEPARATOR.$name;

        if (File::isDirectory($destination)) {
            File::deleteDirectory($destination);
        }

        $this->lock->remove($name);
    }

    public function installedPath(): string
    {
        /** @var array<string, string> $paths */
        $paths = $this->config->get('ai-sdk-toolbox.skills.paths', []);

        return $paths['installed'] ?? storage_path('app/ai/skills');
    }

    /**
     * Copy the source's tools/clis directory into the CLI tools path and
     * register each CLI in the lock with its required environment variables.
     *
     * @return array<int, CliTool>
     */
    public function installCliTools(string $root, string $source, ?string $version): array
    {
        $clisPath = $root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'clis';

        if (! is_dir($clisPath)) {
            return [];
        }

        $destination = $this->cliToolsPath().DIRECTORY_SEPARATOR.$this->sourceSlug($source);
        File::ensureDirectoryExists($destination);
        File::copyDirectory($root.DIRECTORY_SEPARATOR.'tools', $destination);

        /** @var array<string, string> $runtimes */
        $runtimes = $this->config->get('ai-sdk-toolbox.scripts.runtimes', []);
        $tools = [];

        foreach (glob($destination.DIRECTORY_SEPARATOR.'clis'.DIRECTORY_SEPARATOR.'*.js') ?: [] as $file) {
            $name = basename($file, '.js');

            $tool = new CliTool(
                name: $name,
                path: $file,
                runtime: $runtimes['js'] ?? 'node',
                source: $source,
                trust: Trust::Untrusted,
                env: $this->cliScanner->requiredEnvironment($file),
                version: $version,
            );

            $hash = hash_file('sha256', $file);

            $this->lock->putCliTool($tool, $hash === false ? '' : $hash);
            $tools[] = $tool;
        }

        return $tools;
    }

    public function cliToolsPath(): string
    {
        $path = $this->config->get('ai-sdk-toolbox.cli_tools.path');

        return is_string($path) ? $path : storage_path('app/ai/tools');
    }

    private function sourceSlug(string $source): string
    {
        $slug = preg_replace('#^(https?://|git@)[^/:]*[:/]#', '', $source) ?? $source;
        $slug = preg_replace('/\.git$/', '', $slug) ?? $slug;
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $slug) ?? $slug;

        return mb_strtolower(mb_trim($slug, '-'));
    }

    /**
     * @param  callable(ScanReport): bool|null  $confirm
     *
     * @throws InvalidSkillException
     * @throws SkillInstallException
     */
    private function installFromDirectory(string $skillDirectory, string $source, ?string $version, bool $force, ?callable $confirm, bool $strictDirectory = true): InstallResult
    {
        $skill = $this->parser->parse($skillDirectory.DIRECTORY_SEPARATOR.'SKILL.md', $source, Trust::Untrusted, $strictDirectory);

        $destination = $this->installedPath().DIRECTORY_SEPARATOR.$skill->name;

        if (File::isDirectory($destination)) {
            throw SkillInstallException::alreadyInstalled($skill);
        }

        $report = $this->scanner->scan($skill);

        if ($report->verdict() === Verdict::Blocked && ! $force) {
            throw SkillInstallException::blocked($report);
        }

        if ($report->verdict() === Verdict::Warnings && $confirm !== null && ! $confirm($report)) {
            throw SkillInstallException::aborted();
        }

        File::copyDirectory($skillDirectory, $destination);

        $installed = $this->parser->parse($destination.DIRECTORY_SEPARATOR.'SKILL.md', $source, Trust::Untrusted);

        $this->lock->put($installed, $version, Trust::Untrusted);

        return new InstallResult($installed, $report, $destination, $version);
    }

    /**
     * @return array{0: string, 1: string|null, 2: callable}
     */
    private function resolveSourceRoot(string $source): array
    {
        $source = self::normalizeSource($source);

        if (File::isDirectory($source)) {
            return [$source, $this->gitHead($source), static function (): void {}];
        }

        if (preg_match('#^(https?://|git@)#', $source) === 1) {
            $temporary = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ai-skill-'.Str::random(8);

            $result = Process::timeout(120)->run(['git', 'clone', '--depth', '1', $source, $temporary]);

            if ($result->failed()) {
                throw SkillInstallException::cloneFailed($source, $result->errorOutput());
            }

            $version = $this->gitHead($temporary);

            return [$temporary, $version, static function () use ($temporary): void {
                File::deleteDirectory($temporary);
            }];
        }

        throw SkillInstallException::unsupportedSource($source);
    }

    /**
     * @throws SkillInstallException
     */
    private function resolveSkillDirectory(string $root, ?string $path): string
    {
        if ($path !== null) {
            $directory = $root.DIRECTORY_SEPARATOR.$path;

            if (! is_file($directory.DIRECTORY_SEPARATOR.'SKILL.md')) {
                throw SkillInstallException::skillFileNotFound($directory);
            }

            return $directory;
        }

        if (is_file($root.DIRECTORY_SEPARATOR.'SKILL.md')) {
            return $root;
        }

        $candidates = $this->discoverSkillDirectories($root);

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        throw SkillInstallException::ambiguousSkillDirectory(
            $root,
            array_map(fn (string $directory): string => mb_ltrim(mb_substr($directory, mb_strlen($root)), DIRECTORY_SEPARATOR), $candidates),
        );
    }

    /**
     * @return array<int, string>
     */
    private function discoverSkillDirectories(string $root): array
    {
        $directories = [];

        if (is_file($root.DIRECTORY_SEPARATOR.'SKILL.md')) {
            $directories[] = $root;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        $iterator->setMaxDepth(2);

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getFilename() === 'SKILL.md') {
                $directory = $file->getPath();

                if (! str_contains($directory, DIRECTORY_SEPARATOR.'.git')) {
                    $directories[] = $directory;
                }
            }
        }

        $directories = array_values(array_unique($directories));
        sort($directories);

        return $directories;
    }

    private function gitHead(string $directory): ?string
    {
        $result = Process::run(['git', '-C', $directory, 'rev-parse', 'HEAD']);

        return $result->successful() ? mb_trim($result->output()) : null;
    }
}
