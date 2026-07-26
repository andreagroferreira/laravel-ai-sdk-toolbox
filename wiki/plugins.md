# Plugins

Plugins are versioned bundles: **skills + named agents + event listeners** in one installable unit, with an enable/disable lifecycle. A skill gives an agent one capability; a plugin gives your whole application a feature.

---

## What a plugin is

A directory with an `ai-plugin.json` manifest:

```
memory-pack/
├── ai-plugin.json
├── skills/
│   └── memory-tip/
│       └── SKILL.md
└── src/
    ├── Agents/MemoryAgent.php
    └── Listeners/LogToolCalls.php
```

```json
{
    "name": "memory-pack",
    "version": "1.2.0",
    "description": "Memory capabilities for agents.",
    "skills": "./skills",
    "agents": {
        "memory-agent": "Acme\\MemoryPack\\Agents\\MemoryAgent"
    },
    "listeners": {
        "ToolInvoked": ["Acme\\MemoryPack\\Listeners\\LogToolCalls"]
    }
}
```

| Field | Required | Meaning |
|---|---|---|
| `name` | yes | Kebab-case, unique across plugins |
| `version` | yes | Free-form, shown in `ai:plugin-list` |
| `description` | no | Human description |
| `skills` | no | Path to a skills directory (string or first of a list) — wired into the SkillRegistry as source `plugin:<name>` |
| `agents` | no | Map of agent name → agent class, resolvable via the AgentRegistry |
| `listeners` | no | Map of [AI SDK event](../wiki/../) names (e.g. `ToolInvoked`) or FQCNs → listener classes, registered while the plugin is enabled |

The **Claude plugin format** (`.claude-plugin/plugin.json`) is also accepted — name, version, description and `skills` are mapped automatically.

## Lifecycle

```bash
php artisan ai:plugin-install ./packages/memory-pack
php artisan ai:plugin-install acme/memory-pack          # GitHub shorthand
php artisan ai:plugin-install acme/memory-pack --disabled

php artisan ai:plugin-list
php artisan ai:plugin-disable memory-pack
php artisan ai:plugin-enable memory-pack
php artisan ai:plugin-remove memory-pack
```

State lives in `ai-plugins.lock` (commit it):

```json
{
    "plugins": {
        "memory-pack": {
            "version": "1.2.0",
            "path": "storage/app/ai/plugins/memory-pack",
            "source": "acme/memory-pack",
            "enabled": true,
            "installed_at": "2026-07-26T20:00:00+01:00"
        }
    }
}
```

Every transition dispatches an event for your audit log: `PluginInstalled`, `PluginEnabled`, `PluginDisabled`, `PluginRemoved`.

## What "enabled" wires in

At boot, every enabled plugin is wired — failures in one never block the others:

1. **Skills**: the plugin's skills directory joins the SkillRegistry as source `plugin:<name>` — agents can apply them like any other skill
2. **Listeners**: registered against the AI SDK's events (`ToolInvoked`, `AgentPrompted`, `EmbeddingsGenerated`... full FQCNs also accepted)
3. **Agents**: registered by name in the AgentRegistry

## Named agents

The AI SDK has no agent registry — agents are referenced by class. Plugins name theirs so they can be resolved dynamically:

```php
use AndreAgroFerreira\AiSdkToolbox\Facades\AiSdkToolbox;

$agent = AiSdkToolbox::agents()->make('memory-agent', scope: 'tenant-1');

// Compose sub-agents by name:
final class SupportAgent implements Agent, HasTools
{
    use Promptable;

    public function tools(): iterable
    {
        return [
            AiSdkToolbox::agents()->make('memory-agent'),
        ];
    }
}
```

Constructor arguments are forwarded through the container, and `make()` validates the resolved class is actually an AI agent.

## Distribution via Composer

A package declares its plugin in `composer.json`:

```json
{
    "extra": {
        "laravel-ai": {
            "plugin": "resources/plugin"
        }
    }
}
```

Any app requiring it auto-registers the plugin (enabled) at boot — skills, listeners and named agents included. This is the distribution model for internal platforms: one `your-company/ai-plugins` package, every project gets the same capabilities.

## Writing your first plugin (10 minutes)

**1. Manifest** (`memory-pack/ai-plugin.json` — see above).

**2. A skill** (`skills/memory-tip/SKILL.md`):

```markdown
---
name: memory-tip
description: Tips on using the agent memory tools.
---

# Memory Tip

When the user asks you to remember something, confirm what was saved.
```

**3. A named agent:**

```php
final class MemoryAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are the memory agent.';
    }
}
```

**4. A listener** for any of the 27 AI SDK events:

```php
use Laravel\Ai\Events\ToolInvoked;

final class LogToolCalls
{
    public function handle(ToolInvoked $event): void
    {
        Log::info('Tool invoked', ['tool' => $event->tool ?? null]);
    }
}
```

**5. Install:**

```bash
php artisan ai:plugin-install ./memory-pack
php artisan ai:skill-list    # memory-tip is there (source: plugin:memory-pack)
```

Done — your application gained a feature bundle that any project can install the same way.

## Testing plugins

Everything is plain PHP: the manifest parser, the registry (JSON file), the wiring. Feature-test the lifecycle with a fixture plugin in your app or in the package itself — see `tests/Feature/PluginsTest.php` for a complete example.
