# Composite Skills

Markdown-only skills change how an agent *talks*. Composite skills change what an agent can *do* — they ship real PHP tools and middleware alongside the instructions.

---

## Why composite skills exist

The Claude skill format is intentionally code-free: `SKILL.md` plus resources. That's perfect for prompt-level behavior, but agents frequently need executable capabilities that belong with the skill — a "remember" skill needs a `SaveMemory` tool, a "project-info" skill needs a tool that reads the app's metadata, a "logging" skill needs middleware that records every prompt.

Composite skills close that gap **without breaking compatibility**: the `provider` frontmatter key is ignored by Claude, but activates PHP capabilities in the toolbox.

## Anatomy

```markdown
---
name: project-info
description: Answer questions about this project and run diagnostics.
provider: App\Ai\Skills\ProjectInfo\ProjectInfoProvider
---

# Project Info

When the user asks about the project (name, stack, versions),
use the ProjectInfo tool instead of guessing.
```

The provider class implements `ProvidesSkillCapabilities`:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Skills\ProjectInfo;

use AndreAgroFerreira\AiSdkToolbox\Skills\Contracts\ProvidesSkillCapabilities;

final class ProjectInfoProvider implements ProvidesSkillCapabilities
{
    /**
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    public function tools(): array
    {
        return [new ProjectInfoTool];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new PromptLogger];
    }
}
```

A tool is any AI SDK tool:

```php
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ProjectInfoTool implements Tool
{
    public function description(): string
    {
        return 'Get information about this project: name, Laravel and PHP versions.';
    }

    public function handle(Request $request): string
    {
        return sprintf('Project: %s | Laravel: %s | PHP: %s',
            config('app.name'), app()->version(), PHP_VERSION);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
```

When an agent applies `project-info`, `withSkillTools()` merges `ProjectInfoTool` into its tools, and `withSkillMiddleware()` merges `PromptLogger`.

## The composite gate: trust controls code

**Markdown is content; PHP is code.** The gate between them is the core of the security model:

```php
// config/ai-sdk-toolbox.php
'composite' => [
    'allow_from' => ['trusted'],
],
```

| Skill trust | Markdown instructions | PHP tools & middleware |
|---|---|---|
| trusted | applied | applied |
| untrusted | applied | **ignored** |

An untrusted skill still teaches the agent through its markdown — but its PHP provider never loads. To unlock the provider, review the skill and promote it:

```bash
php artisan ai:skill-audit remember
php artisan ai:skill-trust remember
```

To accept composite code from untrusted sources globally (not recommended for production):

```php
'composite' => [
    'allow_from' => ['trusted', 'untrusted'],
],
```

## Provider resolution rules

- The `provider` value must be a fully-qualified class name, resolvable through the container (`app($provider)`) — constructor injection works
- If the class doesn't exist, the skill degrades gracefully to instruction-only (no exception)
- The same provider instance methods are called per merge — keep providers stateless

## Distribution patterns

| Pattern | How |
|---|---|
| **First-party, in-app** | Class in `app/Ai/Skills/...`, skill in `resources/ai/skills/...` — trusted by default |
| **Composer package** | Provider in the package's `src/`, skill in the path declared via `extra.laravel-ai.skills` — untrusted until promoted |
| **Shared internal package** | One `your-company/ai-skills` package with all your composite skills — one place to maintain, every project benefits |

## Full example: a "remember" composite skill

```
remember/
└── SKILL.md
```

```markdown
---
name: remember
description: Save important facts to the project's long-term memory.
provider: App\Ai\Skills\Remember\RememberProvider
---

# Remember

When the user asks you to remember something — a convention, a
decision, a gotcha — use the SaveMemory tool immediately.
```

```php
final class RememberProvider implements ProvidesSkillCapabilities
{
    public function tools(): array
    {
        return [new SaveMemoryTool];
    }

    public function middleware(): array
    {
        return [];
    }
}

final class SaveMemoryTool implements Tool
{
    public function description(): string
    {
        return 'Save a fact to the project memory log (storage/app/ai/memory.md).';
    }

    public function handle(Request $request): string
    {
        $fact = $request['fact'];

        if (! is_string($fact) || mb_trim($fact) === '') {
            return 'Nothing to remember.';
        }

        Storage::disk('local')->append('ai/memory.md', '- '.$fact);

        return 'Remembered: '.$fact;
    }

    public function schema(JsonSchema $schema): array
    {
        return ['fact' => $schema->string()->required()];
    }
}
```

Apply it to an agent:

```php
public function skills(): array
{
    return ['remember'];
}
```

From then on: *"remember that our CI requires Node 20"* → the agent calls `SaveMemory` and the fact lands in `storage/app/ai/memory.md`.

## Tips

- **One concern per skill.** A skill that does five things becomes untestable and unpromotable.
- **Instructions should mention the tools.** The model discovers them via the tool list, but explicit references ("use the SaveMemory tool") improve reliability.
- **Test providers like any class.** They are plain PHP — no special harness needed. Test the merge via an agent's `tools()` in a feature test.

## Next steps

- Give skills executable scripts: [Script Execution](script-execution.md)
- Install third-party CLI tools with env injection: [CLI Tools](cli-tools.md)
- Review what untrusted skills can and cannot do: [Security Model](security-model.md)
