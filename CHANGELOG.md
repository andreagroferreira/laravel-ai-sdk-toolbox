# Changelog

All notable changes to `laravel-ai-sdk-toolbox` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
