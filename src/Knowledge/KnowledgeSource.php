<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

final readonly class KnowledgeSource
{
    /**
     * @param  array<int, string>  $extensions
     */
    public function __construct(
        public string $name,
        public string $disk,
        public string $path,
        public string $namespace,
        public string $chunker,
        public array $extensions,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $name, array $config): self
    {
        $extensions = $config['extensions'] ?? ['md', 'txt', 'html', 'pdf'];
        $extensions = is_array($extensions) ? array_values(array_filter($extensions, is_string(...))) : [];

        $disk = $config['disk'] ?? 'local';
        $path = $config['path'] ?? '';
        $namespace = $config['namespace'] ?? 'default';
        $chunker = $config['chunker'] ?? 'markdown';

        return new self(
            name: $name,
            disk: is_string($disk) ? $disk : 'local',
            path: is_string($path) ? $path : '',
            namespace: is_string($namespace) ? $namespace : 'default',
            chunker: is_string($chunker) ? $chunker : 'markdown',
            extensions: $extensions,
        );
    }
}
