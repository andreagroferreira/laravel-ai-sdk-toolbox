<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Classes\Remember;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SaveMemoryTool implements Tool
{
    public function description(): string
    {
        return 'Save a fact to the agent memory.';
    }

    public function handle(Request $request): string
    {
        $fact = $request['fact'];

        return is_string($fact) ? 'Remembered: '.$fact : 'Nothing to remember.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'fact' => $schema->string()->required(),
        ];
    }
}
