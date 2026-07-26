# Configuration Reference

Publish the configuration with:

```bash
php artisan vendor:publish --tag=ai-sdk-toolbox-config
```

Everything below is the full reference of `config/ai-sdk-toolbox.php` with defaults.

---

## `skills` — paths, trust and the composite gate

```php
'skills' => [
    'paths' => [
        'local' => resource_path('ai/skills'),
        'installed' => storage_path('app/ai/skills'),
    ],

    'trust' => [
        'default' => 'untrusted',
        'sources' => [
            'local' => 'trusted',
            'installed' => 'untrusted',
        ],
    ],

    'composite' => [
        'allow_from' => ['trusted'],
    ],
],
```

| Key | Type | Default | Meaning |
|---|---|---|---|
| `paths` | `array<string, string>` | local + installed | Named directories scanned for skills (each subdir with a `SKILL.md`). The key is the "source" name used by trust |
| `trust.default` | `'trusted'\|'untrusted'` | `untrusted` | Trust level for sources not listed in `trust.sources` (including `composer:*`) |
| `trust.sources` | `array<string, string>` | local: trusted, installed: untrusted | Trust level per source. Per-skill lockfile entries override this |
| `composite.allow_from` | `array<string>` | `['trusted']` | Trust levels allowed to load PHP providers (tools/middleware) |

## `scripts` — skill script execution

```php
'scripts' => [
    'enabled' => env('AI_TOOLBOX_SCRIPTS_ENABLED', true),
    'approval' => env('AI_TOOLBOX_SCRIPTS_APPROVAL', 'untrusted'),
    'timeout' => env('AI_TOOLBOX_SCRIPTS_TIMEOUT', 30),
    'max_output' => 65536,
    'runtimes' => [
        'py' => env('AI_TOOLBOX_PYTHON_BINARY', 'python3'),
        'js' => env('AI_TOOLBOX_NODE_BINARY', 'node'),
        'mjs' => env('AI_TOOLBOX_NODE_BINARY', 'node'),
    ],
    'environment' => ['PATH', 'HOME', 'LANG'],
],
```

| Key | Type | Default | Meaning |
|---|---|---|---|
| `enabled` | `bool` | `true` | Master switch for `RunSkillScript` (skills then contribute instructions and PHP tools only) |
| `approval` | `'untrusted'\|'always'\|'never'` | `untrusted` | When script runs require human approval |
| `timeout` | `int` (seconds) | `30` | Hard limit per script run |
| `max_output` | `int` (bytes) | `65536` | Truncation applied to stdout/stderr before returning to the model |
| `runtimes` | `array<string, string>` | py/js/mjs | Allowed extensions → interpreter binary. Extensions not listed never execute |
| `environment` | `array<string>` | `['PATH','HOME','LANG']` | The only base variables passed to child processes |

## `cli_tools` — third-party CLI tools

```php
'cli_tools' => [
    'enabled' => env('AI_TOOLBOX_CLI_TOOLS_ENABLED', true),
    'path' => storage_path('app/ai/tools'),
],
```

| Key | Type | Default | Meaning |
|---|---|---|---|
| `enabled` | `bool` | `true` | Master switch for `RunCliTool` and CLI execution |
| `path` | `string` | `storage/app/ai/tools` | Where sources' `tools/clis` are installed. Not web-accessible; point outside the document root for extra hardening |

## `http` — the management API

```php
'http' => [
    'enabled' => env('AI_TOOLBOX_HTTP_ENABLED', false),
    'prefix' => env('AI_TOOLBOX_HTTP_PREFIX', 'ai-toolbox'),
    'middleware' => ['web'],
    'throttle' => '10,1',
    'allow_force' => env('AI_TOOLBOX_HTTP_ALLOW_FORCE', false),
],
```

| Key | Type | Default | Meaning |
|---|---|---|---|
| `enabled` | `bool` | `false` | When off, management routes answer 404 |
| `prefix` | `string` | `ai-toolbox` | URL prefix for every management endpoint |
| `middleware` | `array<string>` | `['web']` | Route middleware. Use `['api', 'auth:sanctum']` for token APIs |
| `throttle` | `string` | `10,1` | Rate limit (`attempts,minutes`) for the install endpoint |
| `allow_force` | `bool` | `false` | Allow `force: true` in install payloads (scan-BLOCKED skills) |

## Environment variables

| Variable | Default | Maps to |
|---|---|---|
| `AI_TOOLBOX_SCRIPTS_ENABLED` | `true` | `scripts.enabled` |
| `AI_TOOLBOX_SCRIPTS_APPROVAL` | `untrusted` | `scripts.approval` |
| `AI_TOOLBOX_SCRIPTS_TIMEOUT` | `30` | `scripts.timeout` |
| `AI_TOOLBOX_PYTHON_BINARY` | `python3` | `scripts.runtimes.py` |
| `AI_TOOLBOX_NODE_BINARY` | `node` | `scripts.runtimes.js/.mjs` |
| `AI_TOOLBOX_CLI_TOOLS_ENABLED` | `true` | `cli_tools.enabled` |
| `AI_TOOLBOX_HTTP_ENABLED` | `false` | `http.enabled` |
| `AI_TOOLBOX_HTTP_PREFIX` | `ai-toolbox` | `http.prefix` |
| `AI_TOOLBOX_HTTP_ALLOW_FORCE` | `false` | `http.allow_force` |

## Programmatic configuration

```php
// Authorization for the HTTP layer (AppServiceProvider::boot)
AiSdkToolbox::authorize(fn ($user) => $user?->isAdmin() === true);

// Register an additional skills path at runtime (e.g. from a plugin)
app(SkillRegistry::class)->addPath('plugin:memory-pack', $path);
```
