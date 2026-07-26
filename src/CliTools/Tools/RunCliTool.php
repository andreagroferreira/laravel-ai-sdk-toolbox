<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\CliTools\Tools;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliToolRegistry;
use AndreAgroFerreira\AiSdkToolbox\CliTools\Exceptions\CliToolNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\Exceptions\ScriptExecutionException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\ScriptExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class RunCliTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * @param  array<int, string>  $tools
     */
    public function __construct(
        private readonly array $tools = [],
    ) {}

    public function description(): string
    {
        $names = $this->tools === []
            ? $this->registry()->all()->map->name->all()
            : $this->tools;

        if ($names === []) {
            return 'Run a registered CLI tool. (No CLI tools are currently registered.)';
        }

        return sprintf('Run a registered CLI tool. Available tools: %s.', implode(', ', $names));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'cli' => $schema->string()->required(),
            'args' => $schema->array()->items($schema->string()),
        ];
    }

    public function handle(Request $request): string
    {
        $name = $request['cli'];

        if (! is_string($name)) {
            return 'Error: the [cli] argument must be a string.';
        }

        try {
            $tool = $this->registry()->resolve($name);
        } catch (CliToolNotFoundException $cliToolNotFoundException) {
            return 'Error: '.$cliToolNotFoundException->getMessage();
        }

        if ($this->tools !== [] && ! in_array($tool->name, $this->tools, true)) {
            return sprintf('Error: the CLI tool [%s] is not available in this agent.', $tool->name);
        }

        $rawArgs = $request['args'] ?? [];
        $args = is_array($rawArgs) ? array_values(array_filter($rawArgs, is_string(...))) : [];

        try {
            $result = $this->executor()->runCli($tool, $args);
        } catch (ScriptExecutionException $scriptExecutionException) {
            return 'Error: '.$scriptExecutionException->getMessage();
        }

        if ($result->successful()) {
            return $result->output;
        }

        return sprintf(
            "The CLI tool exited with code %d.\n%s",
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

        $name = $request['cli'];

        if (! is_string($name)) {
            return false;
        }

        try {
            $tool = $this->registry()->resolve($name);
        } catch (CliToolNotFoundException) {
            return false;
        }

        if ($mode === 'always') {
            return Approval::required(sprintf('Run the CLI tool [%s]?', $tool->name));
        }

        return $tool->trust->isTrusted()
            ? false
            : Approval::required(sprintf('Run the CLI tool [%s] from an untrusted source?', $tool->name));
    }

    private function registry(): CliToolRegistry
    {
        return app(CliToolRegistry::class);
    }

    private function executor(): ScriptExecutor
    {
        return app(ScriptExecutor::class);
    }
}
