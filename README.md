# Dev Toolkit

[![GitHub](https://img.shields.io/badge/GitHub-asheek21--baaboo%2Fdev--toolkit-blue)](https://github.com/asheek21-baaboo/dev-toolkit)

Interactive Laravel CLI for bootstrapping common development tools, Composer scripts, Larastan configuration, and Git pre-commit hooks in one command.

**Repository:** [github.com/asheek21-baaboo/dev-toolkit](https://github.com/asheek21-baaboo/dev-toolkit)
## Requirements

- PHP 8.1+
- Laravel 10 or 11
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) and npm (for Husky pre-commit hooks)

## Installation

Add the package to your Laravel project from [GitHub](https://github.com/asheek21-baaboo/dev-toolkit).

Add the VCS repository to your Laravel app's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/asheek21-baaboo/dev-toolkit"
        }
    ],
    "require-dev": {
        "mohammed-asheek/dev-toolkit": "dev-main"
    }
}
```

Then install:

```bash
composer update mohammed-asheek/dev-toolkit
```

Or in one step (after adding the `repositories` entry):

```bash
composer require --dev mohammed-asheek/dev-toolkit:dev-main
```

**Local path (while developing this package):**

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../dev-toolkit"
        }
    ],
    "require-dev": {
        "mohammed-asheek/dev-toolkit": "*"
    }
}
```

Laravel auto-discovers the service provider and registers the `dev-toolkit:install` Artisan command.
## Quick Start

From your Laravel project root:

```bash
php artisan dev-toolkit:install
```

An interactive multiselect prompt lets you choose which packages to install. All options are selected by default — use space to toggle, then press Enter to confirm.

You can re-run this command anytime to install additional tools or refresh configuration.

## What Gets Installed

### Optional Composer packages

| Package | Type | Purpose |
|---------|------|---------|
| `mallardduck/blade-lucide-icons` | Production | Lucide icons for Blade |
| `masmerise/livewire-toaster` | Production | Toast notifications for Livewire |
| `mcamara/laravel-localization` | Production | Multi-language routing and localization |
| `barryvdh/laravel-ide-helper` | Dev | IDE autocompletion for Eloquent models |
| `larastan/larastan` | Dev | Static analysis for Laravel (PHPStan) |

### Composer scripts

The installer adds these shortcuts to your project's `composer.json`:

```bash
composer phpstan      # Run Larastan / PHPStan
composer ide-helper   # Regenerate Eloquent model IDE helper mixins
```

### Larastan (when selected)

If you choose **Larastan (Dev)**, the installer:

1. Creates `phpstan.neon` (only if it does not already exist) with Laravel-focused defaults:
   - Scans `app`, `routes`, `database/seeders`, and `database/factories`
   - Uses `_ide_helper_models.php` for better model type inference
   - Excludes tests, migrations, storage, and other non-application paths
   - Enables Laravel-specific PHPStan parameters
2. Generates `phpstan-baseline.neon` to capture existing issues (skipped if a baseline already exists)
3. Adds the baseline to the `includes` section of `phpstan.neon`

Run analysis manually:

```bash
composer phpstan
# or
vendor/bin/phpstan analyse
```

Regenerate the baseline after fixing issues or when intentionally accepting new ones:

```bash
vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon
```

### Husky pre-commit hook

The installer always sets up [Husky](https://typicode.github.io/husky/) with a `.husky/pre-commit` hook that runs on every commit:

1. **Pint** — checks formatting on dirty files (`vendor/bin/pint --dirty --test`)
2. **Larastan** — runs static analysis (`vendor/bin/phpstan analyse`)

Fix formatting issues before committing:

```bash
vendor/bin/pint --dirty
```

### Skip hooks temporarily

Set `SKIP_HOOKS=1` to bypass the pre-commit hook for a single commit:

```bash
# Linux / macOS / Git Bash
SKIP_HOOKS=1 git commit -m "WIP"

# Windows PowerShell
$env:SKIP_HOOKS=1; git commit -m "WIP"

# Windows CMD
set SKIP_HOOKS=1 && git commit -m "WIP"
```

## Typical Workflow

```bash
# 1. Add the GitHub repo to composer.json, then install
composer require --dev mohammed-asheek/dev-toolkit:dev-main

# 2. Run the interactive installer
php artisan dev-toolkit:install

# 3. Commit the generated files
git add composer.json composer.lock phpstan.neon phpstan-baseline.neon .husky/
git commit -m "Add dev toolkit setup"

# 4. Day-to-day usage
composer phpstan
composer ide-helper
vendor/bin/pint --dirty
```

## Prerequisites for Full Setup

For everything to work end-to-end, your Laravel project should already have:

- **Laravel Pint** — required by the pre-commit hook (`laravel/pint` is included in new Laravel apps)
- **Git** — Husky hooks run on `git commit`
- **npm** — Husky is installed via npm

If Pint or Larastan are not installed, the pre-commit hook will fail until you install them (Larastan is installed when you select it in the installer).

## Windows Notes

- Husky runs hooks through Node.js, so Git for Windows with npm available in your PATH is usually sufficient.
- Use Git Bash or ensure your shell can run the hook's `#!/bin/sh` script.
- `chmod` on the hook file may not apply on Windows; Husky handles execution via its own runner.

## Package Structure

```
src/
├── Console/
│   └── InstallToolsCommand.php   # dev-toolkit:install command
└── ToolkitServiceProvider.php    # Registers the Artisan command
```

## License

This package is open-source software. Add your preferred license before publishing.
