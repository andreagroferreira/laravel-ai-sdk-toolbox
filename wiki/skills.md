# Skills

Skills are the heart of this package: portable, shareable capability packs for your agents, in the exact format used by Claude Code.

---

## What a skill is

A skill is a directory with a `SKILL.md` file:

```
tone-of-voice/
├── SKILL.md          # required: frontmatter + markdown instructions
├── reference/        # optional: supporting documents the instructions point to
├── templates/        # optional: reusable templates
└── scripts/          # optional: executable scripts (see script-execution.md)
```

`SKILL.md` has two parts — YAML frontmatter and a markdown body:

```markdown
---
name: tone-of-voice
description: Apply the company tone of voice to every answer.
---

# Tone of Voice

Always answer in European Portuguese (pt-PT), never Brazilian Portuguese.
Be direct and concise. Lead with the answer, then explain.
```

### Frontmatter rules

| Field | Required | Meaning |
|---|---|---|
| `name` | yes | Kebab-case. Must match the directory name (enforced for skills inside directories) |
| `description` | yes | One or two sentences. Shown in `ai:skill-list` and to the agent via `SkillCatalog` |
| `provider` | no | FQCN of a class implementing `ProvidesSkillCapabilities` — makes the skill *composite* (see [Composite Skills](composite-skills.md)) |
| anything else | no | Unknown keys are kept in the skill's frontmatter bag but ignored — full Claude compatibility |

The markdown body is the instruction set injected into the agent's system prompt.

## Where skills live: sources and trust

The registry scans named paths (`ai-sdk-toolbox.skills.paths`):

```php
'paths' => [
    'local' => resource_path('ai/skills'),      // first-party, in your repo
    'installed' => storage_path('app/ai/skills'), // third-party installs
],
```

Each source has a trust level (`ai-sdk-toolbox.skills.trust`):

```php
'trust' => [
    'default' => 'untrusted',
    'sources' => [
        'local' => 'trusted',
        'installed' => 'untrusted',
    ],
],
```

| Source | Default trust | Why |
|---|---|---|
| `local` | **trusted** | Your skills, reviewed by your team, committed to your repo |
| `installed` | **untrusted** | Third-party skills installed via `ai:skill-install` |
| `composer:vendor/pkg` | **untrusted** | Skills discovered in Composer packages |

Per-skill trust recorded in `ai-skills.lock` **overrides** the source level — this is how `ai:skill-trust` promotes an installed skill without touching config.

## Installing skills

### From a GitHub shorthand, git URL, or local path

```bash
php artisan ai:skill-install coreyhaines31/marketingskills --path=skills/copywriting
php artisan ai:skill-install https://github.com/alirezarezvani/claude-skills.git --path=engineering-team/self-improving-agent/skills/remember
php artisan ai:skill-install ./packages/my-internal-skills/invoice-helper
```

### Everything at once

```bash
php artisan ai:skill-install coreyhaines31/marketingskills --all
# Done: 48 installed, 0 skipped, 0 failed.
```

Already-installed skills are skipped; broken ones are reported and don't stop the rest.

### Single-skill repositories

Repos whose root **is** a skill (a `SKILL.md` at the top level, e.g. `hardikpandya/stop-slop`) install directly:

```bash
php artisan ai:skill-install hardikpandya/stop-slop
```

The name in the frontmatter decides the install destination.

### Via Composer

A package can ship skills by declaring them in its `composer.json`:

```json
{
    "extra": {
        "laravel-ai": {
            "skills": "resources/skills"
        }
    }
}
```

Any app requiring that package discovers its skills automatically under the source `composer:vendor/pkg` (untrusted by default).

## Applying skills to agents

The `HasSkills` trait provides three merge helpers. Everything is explicit — no method conflicts with the AI SDK:

```php
final class SupportAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use HasSkills;
    use Promptable;
    use RemembersConversations;

    public function skills(): array
    {
        return ['tone-of-voice', 'copywriting'];
    }

    public function instructions(): string
    {
        return $this->withSkillInstructions('You are our support agent.');
    }

    public function tools(): iterable
    {
        return $this->withSkillTools([new LookupOrder]);
    }

    public function middleware(): array
    {
        return $this->withSkillMiddleware([new LogPrompts]);
    }
}
```

- `withSkillInstructions($base)` — appends each skill's markdown in **declaration order**, delimited and labeled with source and trust, plus a prompt-injection guard line
- `withSkillTools($tools)` — merges your tools + composite skill tools + `SkillCatalog` + `RunSkillScript`/`RunCliTool` when applicable
- `withSkillMiddleware($middleware)` — merges your middleware + composite skill middleware

**Sub-agents are just agents** — any agent returned from another agent's `tools()` can use `HasSkills` independently:

```php
public function tools(): iterable
{
    return [
        new RefundsAgent, // uses HasSkills with its own skill set
    ];
}
```

## The tools agents receive automatically

| Tool | When | What it does |
|---|---|---|
| `SkillCatalog` | agent has any skills | Lists the agent's skills with description, trust and capabilities |
| `RunSkillScript` | any applied skill has scripts | Runs a skill script (see [Script Execution](script-execution.md)) |
| `RunCliTool` | CLI tools are registered | Runs a registered CLI (see [CLI Tools](cli-tools.md)) |

## Command reference

| Command | Purpose |
|---|---|
| `ai:skill {name}` | Scaffold a new local skill |
| `ai:skill-install {source} {--path=} {--all} {--force}` | Install from path / git URL / `vendor/repo` |
| `ai:skill-list` | Table of all registered skills (name, source, trust, provider, scripts, description) |
| `ai:skill-show {name}` | Full details of one skill |
| `ai:skill-remove {name}` | Remove an installed skill and its lock entry |
| `ai:skill-audit {name}` | Re-run the security scanner |
| `ai:skill-verify {name?}` | Verify file integrity against `ai-skills.lock` |
| `ai:skill-trust {name} {--untrust}` | Promote/demote trust |

## Example: a real skill from the wild

The [marketingskills](https://github.com/coreyhaines31/marketingskills) repository ships 48 marketing skills. After `ai:skill-install coreyhaines31/marketingskills --all`:

```php
final class GrowthAgent implements Agent, Conversational, HasTools
{
    use HasSkills;
    use Promptable;
    use RemembersConversations;

    public function instructions(): string
    {
        return $this->withSkillInstructions('You are our growth marketer.');
    }

    public function skills(): array
    {
        return ['pricing', 'copywriting', 'seo-audit', 'cold-email'];
    }

    public function tools(): iterable
    {
        return $this->withSkillTools([]);
    }
}
```

Four proven, community-maintained playbooks — applied with five lines of code, replaceable or updatable without a deploy.

## Next steps

- Give skills real behavior with PHP tools: [Composite Skills](composite-skills.md)
- Run scripts that ship inside skills: [Script Execution](script-execution.md)
- Understand what "untrusted" really gates: [Security Model](security-model.md)
