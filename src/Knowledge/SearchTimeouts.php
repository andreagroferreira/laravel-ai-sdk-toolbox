<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

final class SearchTimeouts
{
    private const int DEFAULT = 120;

    /**
     * Resolve the seconds a search stage may wait. A per-call value wins over
     * the configured one, and anything that is not a positive integer falls
     * back to the default, so a typo can never mean "no limit".
     *
     * @param  'embeddings'|'store'|'reranking'  $stage
     */
    public static function resolve(string $stage, ?int $override): int
    {
        $value = $override ?? config('ai-sdk-toolbox.knowledge.timeouts.'.$stage);

        if (is_bool($value)) {
            return self::DEFAULT;
        }

        $seconds = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        return is_int($seconds) ? $seconds : self::DEFAULT;
    }
}
