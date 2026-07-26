<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Tools;

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\Exceptions\ScriptExecutionException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\ScriptExecutor;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class RunSkillScript implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * @param  array<int, string>  $skills
     */
    public function __construct(
        private readonly array $skills = [],
    ) {}

    public function description(): string
    {
        return 'Run a script provided by an installed skill. Only scripts declared in the skill scripts directory can be executed.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'skill' => $schema->string()->required(),
            'script' => $schema->string()->required(),
            'args' => $schema->array()->items($schema->string()),
        ];
    }

    public function handle(Request $request): string
    {
        $skillName = $request['skill'];
        $script = $request['script'];

        if (! is_string($skillName) || ! is_string($script)) {
            return 'Error: the [skill] and [script] arguments must be strings.';
        }

        try {
            $skill = $this->registry()->resolve($skillName);
        } catch (SkillNotFoundException $skillNotFoundException) {
            return 'Error: '.$skillNotFoundException->getMessage();
        }

        if ($this->skills !== [] && ! in_array($skill->name, $this->skills, true)) {
            return sprintf('Error: the skill [%s] is not available in this agent.', $skill->name);
        }

        $rawArgs = $request['args'] ?? [];
        $args = is_array($rawArgs) ? array_values(array_filter($rawArgs, is_string(...))) : [];

        try {
            $result = $this->executor()->run($skill, $script, $args);
        } catch (ScriptExecutionException $scriptExecutionException) {
            return 'Error: '.$scriptExecutionException->getMessage();
        }

        if ($result->successful()) {
            return $result->output;
        }

        return sprintf(
            "The script exited with code %d.\n%s",
            $result->exitCode,
            $result->errorOutput !== '' ? $result->errorOutput : $result->output,
        );
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        /** @var string $mode */
        $mode = config('ai-sdk-toolbox.scripts.approval', 'untrusted');

        if ($mode === 'never') {
            return false;
        }

        $skillName = $request['skill'];
        $script = $request['script'];

        if (! is_string($skillName) || ! is_string($script)) {
            return false;
        }

        try {
            $skill = $this->registry()->resolve($skillName);
        } catch (SkillNotFoundException) {
            return false;
        }

        if ($mode === 'always') {
            return Approval::required(sprintf('Run the script [%s] from the skill [%s]?', $script, $skill->name));
        }

        return $skill->trust->isTrusted()
            ? false
            : Approval::required(sprintf('Run the script [%s] from the untrusted skill [%s]?', $script, $skill->name));
    }

    private function registry(): SkillRegistry
    {
        return app(SkillRegistry::class);
    }

    private function executor(): ScriptExecutor
    {
        return app(ScriptExecutor::class);
    }
}
