# Script Execution

Skills can ship executable scripts (Python, Node). This page explains how the toolbox runs them without handing the keys of your application to third-party code.

---

## The default posture

Script execution is **on but guarded** by default, and can be disabled entirely:

```ini
AI_TOOLBOX_SCRIPTS_ENABLED=false
```

When disabled, skills contribute only instructions and PHP tools — no script ever runs.

## How it works

A skill with a `scripts/` directory exposes its files in the skill's manifest. When an agent applies such a skill, it automatically receives the `RunSkillScript` tool:

```
backup/
├── SKILL.md
└── scripts/
    ├── backup.py
    └── diagnostics.py
```

The model calls it like any tool:

```
RunSkillScript(skill: 'backup', script: 'backup.py', args: ['daily'])
```

## Execution guarantees (non-negotiable)

Every run through the `LocalScriptExecutor` enforces all of these:

| Guarantee | Implementation |
|---|---|
| **No shell** | Commands run as argument arrays — argument injection is impossible |
| **Declared scripts only** | Only files inside the skill's `scripts/` directory; `..` and escaping symlinks rejected |
| **Scrubbed environment** | The child process is spawned with `env -i` and receives **only** `PATH`, `HOME`, `LANG` (configurable) — `APP_KEY`, database credentials, AWS keys and AI provider keys never leak |
| **Isolated working directory** | The process runs inside the skill directory, never in your project root |
| **Runtime allowlist** | Only configured extensions run: `.py` → `python3`, `.js`/`.mjs` → `node` (binaries configurable) |
| **Timeout** | 30 seconds by default (`scripts.timeout`) |
| **Bounded output** | Output is truncated to 64 KB (`scripts.max_output`) before reaching the model |

```php
// config/ai-sdk-toolbox.php
'scripts' => [
    'enabled' => env('AI_TOOLBOX_SCRIPTS_ENABLED', true),
    'approval' => env('AI_TOOLBOX_SCRIPTS_APPROVAL', 'untrusted'),
    'timeout' => 30,
    'max_output' => 65536,
    'runtimes' => [
        'py' => env('AI_TOOLBOX_PYTHON_BINARY', 'python3'),
        'js' => env('AI_TOOLBOX_NODE_BINARY', 'node'),
        'mjs' => env('AI_TOOLBOX_NODE_BINARY', 'node'),
    ],
    'environment' => ['PATH', 'HOME', 'LANG'],
],
```

> **Dependency policy:** the package never runs `pip` or `npm install` — that would be remote code execution by design. Skill scripts must use the standard library or dependencies you install yourself on the server.

## Human approval

`RunSkillScript` integrates with the AI SDK's [Human Tool Approval](https://laravel.com/docs/ai-sdk#human-tool-approval). The `scripts.approval` config controls when a human must approve each run:

| Mode | Behavior |
|---|---|
| `untrusted` (default) | Scripts from **untrusted** skills pause the agent and wait for approval (path and args visible in the decision) |
| `always` | Every script run requires approval |
| `never` | No approvals (trusted-only setups) |

```php
// The agent receives a pending approval instead of the script output:
if ($response->hasPendingApprovals()) {
    foreach ($response->pendingApprovals as $approval) {
        // $approval->id, $approval->tool ('RunSkillScript'),
        // $approval->arguments (skill, script, args), $approval->reason
    }
}
```

> **Requirement:** approval flows need a `Conversational` agent (e.g. with `RemembersConversations`) so the paused call can be resumed after the human decides.

## Server requirements

| Component | Needed for |
|---|---|
| `proc_open` enabled in PHP | any script execution |
| `pcntl` (Linux) | recommended for hard timeouts |
| `python3` (≥ 3.11) | skills with `.py` scripts |
| `node` (or `bun`) | skills with `.js` scripts |

The installer warns you when a skill's scripts need a runtime that's missing on the machine.

> **Platform note:** execution relies on `env -i`, so it's Unix-only in v0.1. Windows servers are not supported for script execution (skills still work without scripts).

## Example: a diagnostics skill

```
project-info/
├── SKILL.md
└── scripts/
    └── diagnostics.py
```

```python
import platform
import sys

print(f"python {platform.python_version()} ok: {sys.argv[1] if len(sys.argv) > 1 else 'no-args'}")
```

```markdown
---
name: project-info
description: Run diagnostic scripts for this project.
---

# Project Info

When the user asks for a diagnostics check, run the diagnostics script.
```

```php
final class DiagnosticsAgent implements Agent, Conversational, HasTools
{
    use HasSkills;
    use Promptable;
    use RemembersConversations;

    public function instructions(): string
    {
        return $this->withSkillInstructions('You run diagnostics on demand.');
    }

    public function skills(): array
    {
        return ['project-info'];
    }

    public function tools(): iterable
    {
        return $this->withSkillTools([]);
    }
}
```

*User: "run diagnostics with the 'nightly' argument"* → the agent calls `RunSkillScript(skill: 'project-info', script: 'diagnostics.py', args: ['nightly'])` → output: `python 3.13.0 ok: nightly`.

## Writing safe scripts: guidelines for skill authors

- **Read nothing outside your working directory.** The executor pins you to the skill dir, but well-written scripts shouldn't wander anyway.
- **Don't expect secrets.** If your script needs an API key, it's not a script — it's a [CLI tool](cli-tools.md), which has a proper environment-injection model.
- **Fail loudly and early.** Exit non-zero with a clear stderr message; the tool surfaces both to the model.
- **Stay under the output budget.** 64 KB is plenty for a report, not for a database dump.

## Next steps

- Execute registered SaaS CLIs with declared secrets: [CLI Tools](cli-tools.md)
- Understand the approval and trust model end-to-end: [Security Model](security-model.md)
