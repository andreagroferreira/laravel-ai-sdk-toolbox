<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillParser;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;

function tempLockPath(): string
{
    return sys_get_temp_dir().'/ai-skills-test.lock';
}

function lockSkill(string $name = 'tone-of-voice'): Skill
{
    return (new SkillParser)->parse(__DIR__.'/../../Fixtures/skills/'.$name.'/SKILL.md');
}

beforeEach(function (): void {
    File::delete(tempLockPath());
});

afterEach(function (): void {
    File::delete(tempLockPath());
});

it('starts empty', function (): void {
    $lock = new SkillLock(tempLockPath());

    expect($lock->entries())->toBe([])
        ->and($lock->has('tone-of-voice'))->toBeFalse();
});

it('stores entries with per-file hashes', function (): void {
    $lock = new SkillLock(tempLockPath());
    $lock->put(lockSkill(), '1.0.0');

    $entry = $lock->get('tone-of-voice');

    if ($entry === null) {
        Assert::fail('Expected the entry to be stored.');
    }

    expect($entry['version'])->toBe('1.0.0')
        ->and($entry['files'])->toHaveKey('SKILL.md')
        ->and($lock->has('tone-of-voice'))->toBeTrue();

    $decoded = json_decode((string) file_get_contents(tempLockPath()), true);
    $skills = is_array($decoded) ? ($decoded['skills'] ?? null) : null;

    expect(is_array($skills) && isset($skills['tone-of-voice']))->toBeTrue();
});

it('removes entries', function (): void {
    $lock = new SkillLock(tempLockPath());
    $lock->put(lockSkill());
    $lock->remove('tone-of-voice');

    expect($lock->has('tone-of-voice'))->toBeFalse();
});

it('reports no mismatches right after locking', function (): void {
    $lock = new SkillLock(tempLockPath());
    $lock->put(lockSkill());

    expect($lock->mismatches(lockSkill()))->toBe([]);
});

it('reports modified files', function (): void {
    $lock = new SkillLock(tempLockPath());
    $lock->put(lockSkill('backup'));

    $skillFile = __DIR__.'/../../Fixtures/skills/backup/SKILL.md';
    $original = file_get_contents($skillFile);
    file_put_contents($skillFile, $original."\nTampered.\n");

    try {
        $mismatches = $lock->mismatches(lockSkill('backup'));
    } finally {
        file_put_contents($skillFile, $original);
    }

    expect($mismatches)->toContain('SKILL.md (modified)');
});

it('reports added and removed files', function (): void {
    $fixtures = __DIR__.'/../../Fixtures/skills/tone-of-voice';
    $existing = $fixtures.'/existing.txt';
    $added = $fixtures.'/added.txt';

    file_put_contents($existing, 'existing');

    $lock = new SkillLock(tempLockPath());
    $lock->put(lockSkill());

    file_put_contents($added, 'added');
    unlink($existing);

    try {
        $mismatches = $lock->mismatches(lockSkill());
    } finally {
        @unlink($added);
    }

    expect($mismatches)->toContain('added.txt (added)')
        ->and($mismatches)->toContain('existing.txt (removed)');
});

it('reports a marker for skills that are not locked', function (): void {
    $lock = new SkillLock(tempLockPath());

    expect($lock->mismatches(lockSkill()))->toBe(['<not locked>']);
});
