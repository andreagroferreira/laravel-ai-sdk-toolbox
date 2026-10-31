<?php

declare(strict_types=1);

function releaseDoc(string $path): string
{
    return (string) file_get_contents(__DIR__.'/../../'.$path);
}

it('asks to resolve approvals before stopping writes in the upgrade window', function (): void {
    $changelog = releaseDoc('CHANGELOG.md');

    $resolve = mb_strpos($changelog, 'resolve or abandon pending approvals');
    $stop = mb_strpos($changelog, 'stop writes');

    expect($resolve)->toBeInt()
        ->and($stop)->toBeInt()
        ->and($resolve < $stop)->toBeTrue();
});

it('documents the search timeouts and the limits above them', function (string $path): void {
    expect(releaseDoc($path))
        ->toContain('AI_TOOLBOX_KB_EMBEDDINGS_TIMEOUT')
        ->toContain('AI_TOOLBOX_KB_STORE_TIMEOUT')
        ->toContain('AI_TOOLBOX_KB_RERANK_TIMEOUT')
        ->toContain('max_execution_time')
        ->toContain('request_terminate_timeout');
})->with(['wiki/knowledge.md', 'wiki/configuration.md']);

it('tells apps with a published config to add the timeouts block', function (string $path): void {
    expect(releaseDoc($path))->toContain('published config');
})->with(['CHANGELOG.md', 'wiki/configuration.md']);

it('no longer calls a failed reranking silent', function (): void {
    expect(str_contains(releaseDoc('wiki/knowledge.md'), 'degrades silently'))->toBeFalse();
});

it('documents the 0.5.1 release', function (): void {
    expect(releaseDoc('CHANGELOG.md'))->toContain('## [0.5.1]');
});
