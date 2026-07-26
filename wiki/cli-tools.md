# CLI Tools

Some sources ship executable CLIs next to their skills — integrations with SaaS platforms, analytics, ads, CRMs. The toolbox installs them, discovers what credentials each one needs, and lets agents run them with surgical environment injection.

---

## The problem this solves

Repositories like [marketingskills](https://github.com/coreyhaines31/marketingskills) ship two artifacts:

```
marketingskills/
├── skills/           # 48 marketing skills
└── tools/
    ├── REGISTRY.md
    └── clis/         # 60+ Node CLIs: ga4.js, meta-ads.js, klaviyo.js, apollo.js...
```

The skills' instructions tell the agent to run those CLIs — but each CLI needs **its own credentials** (`GA4_ACCESS_TOKEN`, `META_ACCESS_TOKEN`, `KLAVIYO_API_KEY`...). Dumping your whole environment into a third-party process is not an option. Neither is hardcoding keys anywhere.

## What happens at install time

```bash
php artisan ai:skill-install coreyhaines31/marketingskills --all
```

For a source with `tools/clis/*.js`, the installer automatically:

1. **Copies** the tools to `storage/app/ai/tools/<source-slug>/` — not web-accessible, readable only by your app (and never committed)
2. **Statically extracts** every `process.env.*` reference from each CLI and records the required variables
3. **Registers** each CLI in `ai-skills.lock` with a SHA-256 hash of its file, as **untrusted**
4. **Reports** which environment variables you still need to populate

Check the result:

```bash
php artisan ai:tool-list
```

```
+----------------+---------+-------------------------------+-----------+----------------------------------+
| Name           | Runtime | Source                        | Trust     | Required environment             |
+----------------+---------+-------------------------------+-----------+----------------------------------+
| ga4            | node    | coreyhaines31/marketingskills | untrusted | GA4_ACCESS_TOKEN (set)           |
| klaviyo        | node    | coreyhaines31/marketingskills | untrusted | KLAVIYO_API_KEY (MISSING)        |
| meta-ads       | node    | coreyhaines31/marketingskills | untrusted | META_ACCESS_TOKEN (MISSING),     |
|                |         |                               |           | META_AD_ACCOUNT_ID (MISSING)     |
+----------------+---------+-------------------------------+-----------+----------------------------------+
```

> The list shows **set/missing** status only — never the values.

## Runtime: how secrets are injected

When the agent runs a CLI, the executor spawns the process with:

- The base scrubbed environment (`PATH`, `HOME`, `LANG`)
- **Plus only the variables that specific CLI declared** — read from your app's environment at execution time

Your `APP_KEY`, database credentials, and every other provider's keys never reach the child process. A CLI that declares `GA4_ACCESS_TOKEN` cannot see `KLAVIYO_API_KEY` — even if both are set in your `.env`.

```ini
# .env — populate what you actually use
GA4_ACCESS_TOKEN=ya29...
KLAVIYO_API_KEY=pk_...
```

## How agents use CLIs

When CLI tools are registered, skilled agents automatically receive the `RunCliTool` tool. Its description lists the available CLIs, so the model knows what exists:

```
RunCliTool(cli: 'ga4', args: ['report', '--days', '30'])
```

- Only **registered** CLIs run (lockfile entries)
- CLIs failing because of missing credentials return their own clear error (e.g. `{"error": "GA4_ACCESS_TOKEN environment variable required"}`) — the agent can relay it
- Same execution guarantees as scripts: no shell, timeout, truncated output, isolated working directory

## Approval for untrusted CLIs

`RunCliTool` follows the same approval model as scripts (`scripts.approval`): CLIs from **untrusted** sources require human approval per run; promote them individually after review:

```php
AiSdkToolbox::cliTools()->trust('ga4', true);
// or via HTTP: POST /ai-toolbox/cli-tools/ga4/trust
```

## Configuration

```php
// config/ai-sdk-toolbox.php
'cli_tools' => [
    'enabled' => env('AI_TOOLBOX_CLI_TOOLS_ENABLED', true),
    'path' => storage_path('app/ai/tools'),
],
```

| Option | Meaning |
|---|---|
| `enabled` | Master switch for CLI execution (`RunCliTool` is not added to agents when off) |
| `path` | Where third-party CLIs are installed. Point it outside the document root for extra hardening |

## Full example: GA4 analytics in an agent

**1. Install the source** (skills + CLIs come together):

```bash
php artisan ai:skill-install coreyhaines31/marketingskills --all
```

**2. Populate credentials:**

```ini
GA4_ACCESS_TOKEN=ya29.a0...
```

**3. Apply the analytics skill to an agent:**

```php
final class AnalyticsAgent implements Agent, Conversational, HasTools
{
    use HasSkills;
    use Promptable;
    use RemembersConversations;

    public function instructions(): string
    {
        return $this->withSkillInstructions('You answer analytics questions using real data.');
    }

    public function skills(): array
    {
        return ['analytics'];
    }

    public function tools(): iterable
    {
        return $this->withSkillTools([]);
    }
}
```

**4. Ask:** *"how did our traffic do this week?"* → the agent calls `RunCliTool(cli: 'ga4', args: [...])` → real GA4 data in the answer.

## Integrity and updates

CLI files are hashed in `ai-skills.lock` like skills. `ai:skill-verify` reports any tampering:

```bash
php artisan ai:skill-verify
# [ga4] OK.
# cli:klaviyo integrity violations:
#  ⇂ klaviyo.js (modified)
```

Re-installing a source re-copies its CLIs and re-hashes them — explicit, reviewable in git, never automatic.

## Next steps

- The full trust model these features plug into: [Security Model](security-model.md)
- Manage CLIs from a UI or API: [HTTP Management](http-management.md)
