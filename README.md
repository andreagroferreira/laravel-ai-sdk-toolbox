# Laravel AI SDK Toolbox

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andreagroferreira/laravel-ai-sdk-toolbox.svg)](https://packagist.org/packages/andreagroferreira/laravel-ai-sdk-toolbox)
[![Total Downloads](https://img.shields.io/packagist/dt/andreagroferreira/laravel-ai-sdk-toolbox.svg)](https://packagist.org/packages/andreagroferreira/laravel-ai-sdk-toolbox)
[![Tests](https://github.com/andreagroferreira/laravel-ai-sdk-toolbox/actions/workflows/tests.yml/badge.svg)](https://github.com/andreagroferreira/laravel-ai-sdk-toolbox/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/andreagroferreira/laravel-ai-sdk-toolbox.svg)](https://packagist.org/packages/andreagroferreira/laravel-ai-sdk-toolbox)

**The [Laravel AI SDK](https://laravel.com/docs/ai-sdk) on steroids — Claude-compatible skills, executable CLI tools, and a security-first management layer for your agents.**

---

## Why this package exists

The Laravel AI SDK gives you agents: instructions, tools, structured output, conversations. It's a brilliant foundation. But the first time you build something real with it, you run into the same walls we did:

- **Every project reinvents the same agent capabilities.** A "remember this" skill, a "project info" tool, a tone-of-voice instruction block. You copy-paste them from project to project, and they drift apart.
- **There's an entire ecosystem of proven skills you're locked out of.** The Claude Code community has published hundreds of battle-tested `SKILL.md` skills — marketing, SEO, copywriting, code review. Your Laravel agents can't use any of them.
- **Scripts are a trap.** Skills that ship Python or Node utilities can't run in your app without you hand-rolling `Process` calls — and doing it unsafely (leaking `APP_KEY` and database credentials into child processes) is the default outcome.
- **Production is static.** Skills are baked into code. Want to add one at 11pm without a deploy? You can't.

We kept doing this work manually. So we packaged it — with the part nobody should improvise: **a security model for running third-party code**.

## What it does

| Pillar | What you get |
|---|---|
| **Skills** | Install [Claude-format skills](https://agentskills.io) from paths, git URLs, `vendor/repo` shorthands or Composer packages, and apply them to any agent or sub-agent with one trait |
| **Composite skills** | Skills that ship real PHP tools and middleware — capability packs, not just prompt fragments |
| **CLI tools** | Sources shipping `tools/clis/*.js` (marketing registries, SaaS integrations) become agent tools with **per-tool environment injection** — each CLI gets only the secrets it declared |
| **Script execution** | `RunSkillScript` runs skill scripts with a scrubbed environment, path validation, timeouts and **human approval** for untrusted sources |
| **Security-first** | Trust levels, an install-time static scanner, an integrity lockfile, and trust promotion commands |
| **HTTP management** | An opt-in, headless JSON API + PHP managers to manage everything dynamically from Inertia, Livewire, or any API client — no deploys, no CLI access needed |

## Quick start

```bash
composer require andreagroferreira/laravel-ai-sdk-toolbox
```

Install a skill and use it in an agent — that's two steps:

```bash
php artisan ai:skill-install coreyhaines31/marketingskills --path=skills/copywriting
```

```php
use AndreAgroFerreira\AiSdkToolbox\Skills\Concerns\HasSkills;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

final class MarketingAgent implements Agent, HasTools
{
    use Promptable, HasSkills;

    public function instructions(): string
    {
        return $this->withSkillInstructions('You are our marketing assistant.');
    }

    public function skills(): array
    {
        return ['copywriting'];
    }

    public function tools(): iterable
    {
        return $this->withSkillTools([]);
    }
}
```

Your agent now follows the copywriting skill — delimited, flagged as untrusted content, with a guard line against prompt injection. Install 40 more skills without changing a line of code.

## Documentation

The README is the overview. The **wiki/ has a deep-dive per feature** with full implementation examples:

| Guide | Contents |
|---|---|
| [Getting Started](wiki/getting-started.md) | Installation, configuration, your first skilled agent |
| [Skills](wiki/skills.md) | The skill format, registry, sources, commands, trust model |
| [Composite Skills](wiki/composite-skills.md) | Providers, PHP tools, middleware, the composite gate |
| [Script Execution](wiki/script-execution.md) | `RunSkillScript`, executor guarantees, approvals, runtimes |
| [CLI Tools](wiki/cli-tools.md) | `tools/clis`, env extraction & injection, `RunCliTool`, `ai:tool-list` |
| [Security Model](wiki/security-model.md) | Trust levels, scanner, lockfile, integrity verification |
| [HTTP Management](wiki/http-management.md) | Endpoints, authorization, Inertia / Livewire / API usage |
| [Configuration Reference](wiki/configuration.md) | Every config option explained |

## Requirements

| Component | Required | Notes |
|---|---|---|
| PHP 8.4+, Laravel 13+, `laravel/ai` ^0.10 | yes | — |
| `proc_open` enabled | only for scripts & CLI tools | — |
| `python3` / `node` | only if you use them | same on dev and production |

## A note on trust

This package executes third-party content by design. That's exactly why it ships with a trust model instead of pretending the problem doesn't exist: installed skills land **untrusted**, PHP capabilities require trust, untrusted scripts ask a human before running, a static scanner reviews every install, and an integrity lockfile detects tampering. Read the [Security Model](wiki/security-model.md) before going to production — it takes five minutes.

## Testing

```bash
composer test   # pint + phpstan (level max) + pest
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Credits

- [André Agro Ferreira](https://github.com/andreagroferreira)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
