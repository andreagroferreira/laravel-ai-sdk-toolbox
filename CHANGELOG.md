# Changelog

All notable changes to `laravel-ai-sdk-toolbox` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0] - 2026-07-26

### Knowledge HTTP endpoints

- `GET /ai-toolbox/knowledge/documents` (namespace/status filters), `POST /ai-toolbox/knowledge/sync` (throttled), `GET /ai-toolbox/knowledge/search`, `GET /ai-toolbox/knowledge/status` — the knowledge base is now fully manageable from Inertia, Livewire or any API client

### Skill updates

- `ai:skill-update {name}` / `ai:skill-update --all` and `POST /ai-toolbox/skills/{name}/update`: re-resolve the original source, re-scan (same gates as install), replace files, refresh hashes and git SHA in the lock — **trust is preserved** across updates
- New `SkillUpdated` event for audit logs

### Qdrant vector store

- New `QdrantStore` driver (`knowledge.store: 'qdrant'`): zero extra dependencies (HTTP API), one collection with payload-filtered namespaces, atomic document replacement, score-based results with `minSimilarity`
- Config via `knowledge.stores.qdrant` (`url`, `api_key`, `collection`); unit tests with HTTP fakes plus real integration tests in CI (qdrant service, PHP 8.4/8.5)

### Plugin HTTP endpoints (0.3.1 backfill)

- HTTP endpoints for plugins: list, show, install (`POST /ai-toolbox/plugins/install`, throttled), remove, enable and disable — with the same authorization, events and safety model as the rest of the management layer

## [0.3.0] - 2026-07-26

### Plugins

- Versioned plugin bundles: skills + named agents + event listeners in one installable unit (`ai-plugin.json`, with the Claude `.claude-plugin/plugin.json` format also accepted)
- Lifecycle with state in `ai-plugins.lock`: `ai:plugin-install` (path / git URL / `vendor/repo`), `ai:plugin-list`, `ai:plugin-enable`, `ai:plugin-disable`, `ai:plugin-remove` — with events (`PluginInstalled`, `PluginEnabled`, `PluginDisabled`, `PluginRemoved`)
- Enabled plugins are wired at boot: skills join the registry as source `plugin:<name>`, listeners register against the AI SDK events, named agents register in the new `AgentRegistry` (`AiSdkToolbox::agents()->make('name', ...$args)` for dynamic sub-agent composition)
- Composer distribution: packages declare `extra.laravel-ai.plugin` and are auto-registered at boot

## [0.2.0] - 2026-07-26

### Knowledge base

- Unified ingestion pipeline: any Flysystem disk (local, S3, FTP) → extract (md/txt/html, PDF via `smalot/pdfparser`) → chunk (markdown-aware or fixed-size with overlap) → batched, cached embeddings via the AI SDK → pgvector store
- Incremental `ai:kb-sync` with sha256 diffs, deletions, atomic chunk replacement, queued jobs (`--sync` for inline) and `ai:kb-status`
- Isolated namespaces per source; documents tracked with status (`pending`/`synced`/`error`) and events (`KnowledgeDocumentSynced`, `KnowledgeSyncFailed`)
- `KnowledgeSearch` agent tool with optional reranking (degrades gracefully without a reranking provider) and a fluent `Knowledge::namespace()->search()` API
- `VectorStore` contract with a pgvector driver and an in-memory fake for tests; pgvector integration tests run in CI (PostgreSQL service, PHP 8.4/8.5 × prefer-stable/lowest)

## [0.1.0] - 2026-07-26

First release. The Laravel AI SDK on steroids.

### Skills

- Claude-compatible skills (`SKILL.md` with YAML frontmatter) installable from local paths, git URLs, GitHub shorthands (`vendor/repo`) and Composer packages
- `HasSkills` trait for agents and sub-agents with deterministic instruction/tool/middleware merging, delimited skill content and an untrusted-content guard line
- Composite skills: PHP providers contributing real tools and middleware, gated by trust level
- `SkillCatalog` tool added automatically to skilled agents
- Bulk installs with `--all`, per-skill skips/failures and candidate listing on ambiguous sources
- Repositories whose root is a single skill are supported (name↔directory convention only enforced inside directories)

### CLI tools

- Sources shipping `tools/clis/*.js` are installed to `storage/app/ai/tools/<source-slug>/` and registered with per-file SHA-256 hashes
- Static extraction of `process.env.*` per CLI; the executor injects only the declared variables into the child process (default-deny environment)
- `RunCliTool` agent tool with human approval for untrusted sources, and `ai:tool-list` showing required environment status
- `SkillCatalog` and `RunCliTool` added automatically to skilled agents

### Script execution

- `ScriptExecutor` contract + `LocalScriptExecutor`: no shell, scrubbed environment (`env -i`), skill-directory working dir, timeouts, output truncation, runtime allowlist (`.py`, `.js`), path-traversal and symlink protection
- `RunSkillScript` tool with `Approvable` integration (`untrusted`/`always`/`never`)

### Security

- Trust levels (`trusted`/`untrusted`) per source and per skill, with promotion/demotion commands
- Static security scanner at install time: PHP token analysis, script patterns, prompt-injection heuristics — SAFE/WARNINGS/BLOCKED verdicts
- Integrity lockfile (`ai-skills.lock`) with per-file SHA-256 hashes and recorded install paths
- No auto-updates; re-installs are explicit and re-scanned

### HTTP management layer

- Opt-in, headless JSON management API for Inertia, Livewire (via the PHP managers) and Sanctum-based APIs
- Endpoints for listing, installing, removing, auditing, trusting and verifying skills and CLI tools
- Horizon-style authorization via `AiSdkToolbox::authorize()` (local-only by default); blocked skills cannot be force-installed over HTTP; install endpoint throttled; environment values never exposed
- `SkillManager` / `CliToolManager` PHP APIs with events on every mutation (`SkillInstalled`, `SkillUninstalled`, `SkillTrustChanged`, `CliToolsInstalled`, `CliToolTrustChanged`)

### Commands

`ai:skill`, `ai:skill-install`, `ai:skill-list`, `ai:skill-show`, `ai:skill-remove`, `ai:skill-audit`, `ai:skill-verify`, `ai:skill-trust`, `ai:tool-list`
