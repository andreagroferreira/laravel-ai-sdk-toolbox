# HTTP Management

Manage skills and CLI tools dynamically in production — no deploys, no CLI access. The package is **headless**: a PHP manager API plus an opt-in JSON layer, so Inertia, Livewire and plain API consumers each use it their own way.

---

## The three consumption patterns

### 1. Livewire (or any PHP) — the manager API

```php
use AndreAgroFerreira\AiSdkToolbox\Facades\AiSdkToolbox;

final class SkillManager extends Component
{
    public function install(string $source): void
    {
        $result = AiSdkToolbox::skills()->install(
            source: $source,
            all: true,
            acceptWarnings: true,
        );

        session()->flash('status', sprintf('%d skills installed.', $result->installedCount()));
    }

    public function trust(string $name): void
    {
        AiSdkToolbox::skills()->trust($name, true);
    }

    public function render()
    {
        return view('livewire.skill-manager', [
            'skills' => AiSdkToolbox::skills()->all(),
            'cliTools' => AiSdkToolbox::cliTools()->all(),
        ]);
    }
}
```

### 2. Inertia — pages call the JSON endpoints

```ts
const install = (source: string) =>
    axios.post('/ai-toolbox/skills/install', { source, all: true });

const trust = (name: string) =>
    axios.post(`/ai-toolbox/skills/${name}/trust`);
```

### 3. Plain API / Sanctum

```php
// config/ai-sdk-toolbox.php
'http' => [
    'enabled' => true,
    'middleware' => ['api', 'auth:sanctum'],
],
```

## Enabling the HTTP layer

Off by default:

```php
// config/ai-sdk-toolbox.php
'http' => [
    'enabled' => env('AI_TOOLBOX_HTTP_ENABLED', false),
    'prefix' => 'ai-toolbox',
    'middleware' => ['web'],
    'throttle' => '10,1',
    'allow_force' => false,
],
```

| Option | Default | Meaning |
|---|---|---|
| `enabled` | `false` | Master switch (middleware answers 404 when off) |
| `prefix` | `ai-toolbox` | URL prefix for every endpoint |
| `middleware` | `['web']` | Route middleware — swap for `['api', 'auth:sanctum']` |
| `throttle` | `10,1` | Rate limit for the install endpoint |
| `allow_force` | `false` | Allow force-installing scan-BLOCKED skills over HTTP |

## Authorization (Horizon-style)

```php
// App\Providers\AppServiceProvider::boot()
use AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox;

AiSdkToolbox::authorize(fn ($user) => $user?->hasRole('admin') === true);
```

Without a callback, access is granted **only in the `local` environment**. The callback receives the authenticated user (or `null` for guests) and must return a boolean.

## Endpoint reference

| Method | Endpoint | Action |
|---|---|---|
| `GET` | `/ai-toolbox/skills` | List all skills (name, source, trust, provider, scripts) |
| `GET` | `/ai-toolbox/skills/{name}` | Show one skill |
| `POST` | `/ai-toolbox/skills/install` | Install: `{source, path?, all?, accept_warnings?, force?}` |
| `DELETE` | `/ai-toolbox/skills/{name}` | Remove a skill |
| `GET` | `/ai-toolbox/skills/{name}/audit` | Security scan report |
| `POST` | `/ai-toolbox/skills/{name}/trust` | Promote to trusted |
| `DELETE` | `/ai-toolbox/skills/{name}/trust` | Demote to untrusted |
| `GET` | `/ai-toolbox/cli-tools` | List CLIs with env status (set/missing — never values) |
| `POST` | `/ai-toolbox/cli-tools/{name}/trust` | Promote CLI to trusted |
| `DELETE` | `/ai-toolbox/cli-tools/{name}/trust` | Demote CLI |
| `GET` | `/ai-toolbox/plugins` | List installed plugins (version, state, source) |
| `GET` | `/ai-toolbox/plugins/{name}` | Show plugin manifest details |
| `POST` | `/ai-toolbox/plugins/install` | Install a plugin: `{source, path?, disabled?}` |
| `DELETE` | `/ai-toolbox/plugins/{name}` | Remove a plugin |
| `POST` | `/ai-toolbox/plugins/{name}/enable` | Enable a plugin |
| `POST` | `/ai-toolbox/plugins/{name}/disable` | Disable a plugin |
| `GET` | `/ai-toolbox/verify` | Integrity report for skills + CLIs |

### Install response example

```json
{
    "data": {
        "installed": [
            {"name": "copywriting", "source": "coreyhaines31/marketingskills", "trust": "untrusted", "provider": null, "scripts": []}
        ],
        "skipped": [],
        "failed": [],
        "cli_tools": ["ga4", "klaviyo", "meta-ads"]
    }
}
```

## Safety rules (enforced server-side)

- Installs via HTTP always land **untrusted** — promotion is a separate, explicit action
- `force: true` in the payload is rejected with `422` unless `http.allow_force` is on
- Skills with scan warnings need `accept_warnings: true`
- Payloads are validated (source pattern, no `..` in paths)
- Environment variables are reported as set/missing only

## Events for your audit log

Every mutation dispatches an event:

```php
use AndreAgroFerreira\AiSdkToolbox\Events\SkillInstalled;
use AndreAgroFerreira\AiSdkToolbox\Events\SkillTrustChanged;
use AndreAgroFerreira\AiSdkToolbox\Events\SkillUninstalled;
use AndreAgroFerreira\AiSdkToolbox\Events\CliToolsInstalled;
use AndreAgroFerreira\AiSdkToolbox\Events\CliToolTrustChanged;

Event::listen(SkillInstalled::class, function (SkillInstalled $event) {
    Log::info('Skill installed', [
        'skill' => $event->skill->name,
        'source' => $event->skill->source,
        'cli_tools' => array_map(fn ($tool) => $tool->name, $event->cliTools),
        'by' => request()->user()?->email,
    ]);
});
```

## Verify endpoint

```json
{
    "ok": false,
    "data": {
        "remember": ["SKILL.md (modified)"],
        "copywriting": [],
        "cli:ga4": []
    }
}
```

`ok` is `true` only when every locked file matches its hash — wire it into monitoring.

## Building an admin UI: suggested layout

1. **Skills page** — table from `GET /skills` with trust badges, audit and trust/untrust buttons
2. **Install page** — source input with `--all` toggle, renders the scan report before confirming (`accept_warnings`)
3. **CLI tools page** — table from `GET /cli-tools` highlighting MISSING variables
4. **Integrity widget** — `GET /verify` status card on the dashboard
