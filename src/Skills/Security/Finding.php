<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Security;

final readonly class Finding
{
    public function __construct(
        public string $rule,
        public Severity $severity,
        public string $file,
        public string $message,
        public ?int $line = null,
    ) {}
}
