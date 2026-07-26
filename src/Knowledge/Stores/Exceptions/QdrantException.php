<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores\Exceptions;

use RuntimeException;

final class QdrantException extends RuntimeException
{
    public static function requestFailed(string $operation, int $status, string $body): self
    {
        return new self(sprintf('Qdrant [%s] failed with status %d: %s', $operation, $status, mb_strimwidth($body, 0, 500, '…')));
    }
}
