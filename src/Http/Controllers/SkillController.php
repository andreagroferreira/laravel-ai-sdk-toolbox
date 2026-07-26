<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Http\Controllers;

use AndreAgroFerreira\AiSdkToolbox\Http\Requests\InstallSkillRequest;
use AndreAgroFerreira\AiSdkToolbox\Management\SkillManager;
use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\InvalidSkillException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillInstallException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Skills\InstallManyResult;
use AndreAgroFerreira\AiSdkToolbox\Skills\InstallResult;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\Finding;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\ScanReport;
use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class SkillController extends Controller
{
    public function index(SkillManager $manager): JsonResponse
    {
        return new JsonResponse([
            'data' => $manager->all()->map(fn (Skill $skill): array => $this->serializeSkill($skill))->values()->all(),
        ]);
    }

    public function show(SkillManager $manager, string $name): JsonResponse
    {
        try {
            return new JsonResponse(['data' => $this->serializeSkill($manager->find($name))]);
        } catch (SkillNotFoundException) {
            return new JsonResponse(['message' => sprintf('Skill [%s] not found.', $name)], 404);
        }
    }

    public function install(InstallSkillRequest $request, SkillManager $manager): JsonResponse
    {
        if ($request->forced() && ! config('ai-sdk-toolbox.http.allow_force', false)) {
            return new JsonResponse(['message' => 'Force installs are not allowed over HTTP.'], 422);
        }

        try {
            $result = $manager->install(
                source: $request->source(),
                path: $request->subpath(),
                all: $request->installAll(),
                force: $request->forced(),
                acceptWarnings: $request->acceptsWarnings(),
            );
        } catch (SkillInstallException|InvalidSkillException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return new JsonResponse(['data' => $this->serializeResult($result)], 201);
    }

    public function destroy(SkillManager $manager, string $name): JsonResponse
    {
        try {
            $manager->uninstall($name);
        } catch (SkillInstallException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return new JsonResponse(['name' => $name, 'removed' => true]);
    }

    public function update(\Illuminate\Http\Request $request, SkillManager $manager, string $name): JsonResponse
    {
        $force = $request->boolean('force');

        if ($force && ! config('ai-sdk-toolbox.http.allow_force', false)) {
            return new JsonResponse(['message' => 'Force updates are not allowed over HTTP.'], 422);
        }

        try {
            $result = $manager->update($name, $force, $request->boolean('accept_warnings'));
        } catch (SkillInstallException|InvalidSkillException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return new JsonResponse([
            'data' => [
                'skill' => $this->serializeSkill($result->skill),
                'previous_version' => $result->previousVersion,
                'version' => $result->version,
                'report' => $this->serializeReport($result->report),
            ],
        ]);
    }

    public function audit(SkillManager $manager, string $name): JsonResponse
    {
        try {
            $report = $manager->audit($name);
        } catch (SkillNotFoundException) {
            return new JsonResponse(['message' => sprintf('Skill [%s] not found.', $name)], 404);
        }

        return new JsonResponse(['data' => $this->serializeReport($report)]);
    }

    public function trust(SkillManager $manager, string $name): JsonResponse
    {
        $manager->trust($name, true);

        return new JsonResponse(['name' => $name, 'trust' => 'trusted']);
    }

    public function untrust(SkillManager $manager, string $name): JsonResponse
    {
        $manager->trust($name, false);

        return new JsonResponse(['name' => $name, 'trust' => 'untrusted']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSkill(Skill $skill): array
    {
        return [
            'name' => $skill->name,
            'description' => $skill->description,
            'source' => $skill->source,
            'trust' => $skill->trust->value,
            'provider' => $skill->provider,
            'scripts' => $skill->scripts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReport(ScanReport $report): array
    {
        return [
            'skill' => $report->skill->name,
            'verdict' => $report->verdict()->value,
            'findings' => $report->findings->map(fn (Finding $finding): array => [
                'rule' => $finding->rule,
                'severity' => $finding->severity->value,
                'file' => $finding->file,
                'line' => $finding->line,
                'message' => $finding->message,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResult(InstallResult|InstallManyResult $result): array
    {
        if ($result instanceof InstallResult) {
            return [
                'installed' => [$this->serializeSkill($result->skill)],
                'cli_tools' => array_map(fn ($tool): string => $tool->name, $result->cliTools),
                'report' => $this->serializeReport($result->report),
            ];
        }

        return [
            'installed' => array_map(fn (InstallResult $installed): array => $this->serializeSkill($installed->skill), $result->installed),
            'skipped' => $result->skipped,
            'failed' => $result->failed,
            'cli_tools' => array_map(fn ($tool): string => $tool->name, $result->cliTools),
        ];
    }
}
