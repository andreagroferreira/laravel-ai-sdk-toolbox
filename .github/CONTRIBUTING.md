# Contribution Guide

Thank you for considering contributing to the Laravel AI SDK Toolbox.

## Bug Reports

- Search the [existing issues](https://github.com/andreagroferreira/laravel-ai-sdk-toolbox/issues) first.
- Use the **Bug Report** issue template and include: package version, Laravel version, PHP version, a minimal reproduction, and the expected vs. actual behavior.
- Security vulnerabilities are **not** handled via issues — see [SECURITY.md](.github/SECURITY.md).

## Pull Requests

1. Fork the repository and create a feature branch from `main`.
2. Follow the existing code style: `declare(strict_types=1)`, PHP 8.4+ syntax, `final` classes by default, typed everything.
3. Add tests for every behavior change. No feature lands without coverage.
4. Before submitting, the whole suite must pass:

```bash
composer test   # pint --test + phpstan (level max) + pest
```

5. Write [conventional commits](https://www.conventionalcommits.org): `feat(skills): ...`, `fix(cli-tools): ...`, `docs: ...`, `test: ...`, `refactor: ...`, `chore: ...`.
6. Keep PRs focused. One concern per PR.
7. Update `CHANGELOG.md` under `[Unreleased]` and the README when behavior changes.

## Development Setup

```bash
git clone https://github.com/andreagroferreira/laravel-ai-sdk-toolbox.git
cd laravel-ai-sdk-toolbox
composer install
composer test
```

The test suite runs on [Orchestra Testbench](https://github.com/orchestral/testbench) with Pest — no Laravel application required.

## Code of Conduct

Please review our [Code of Conduct](.github/CODE_OF_CONDUCT.md) before participating.
