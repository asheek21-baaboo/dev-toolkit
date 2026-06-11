<?php

namespace MyVendor\DevToolkit\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\multiselect;

class InstallToolsCommand extends Command
{
    protected $signature = 'dev-toolkit:install';
    protected $description = 'Interactively install development tools and setup workflows.';

    public function handle()
    {
        // * lets Composer resolve the latest version compatible with the host Laravel app (11–13)
        $packages = [
            'mallardduck/blade-lucide-icons:*' => 'Blade Lucide Icons',
            'masmerise/livewire-toaster:*' => 'Livewire Toaster',
            'mcamara/laravel-localization:*' => 'Laravel Localization',
            'barryvdh/laravel-ide-helper:*' => 'Laravel IDE Helper (Dev)',
            'larastan/larastan:*' => 'Larastan (Dev)',
        ];

        // Prompt the user (Using Laravel Prompts)
        $selectedLabels = multiselect(
            label: 'Which packages would you like to install or update?',
            options: array_values($packages),
            default: array_values($packages)
        );

        if (empty($selectedLabels)) {
            $this->info('No packages selected. Exiting.');
            return;
        }

        $regularRequires = [];
        $devRequires = [];

        // Separate dependencies into standard and dev
        foreach ($packages as $pkg => $label) {
            if (in_array($label, $selectedLabels)) {
                if (str_contains($label, '(Dev)')) {
                    $devRequires[] = $pkg;
                } else {
                    $regularRequires[] = $pkg;
                }
            }
        }

        if (!empty($regularRequires)) {
            $this->info("\n📦 Installing Regular Packages...");
            if (!$this->runComposerRequire($regularRequires)) {
                return self::FAILURE;
            }
        }

        if (!empty($devRequires)) {
            $this->info("\n📦 Installing Dev Packages...");
            if (!$this->runComposerRequire($devRequires, dev: true)) {
                return self::FAILURE;
            }
        }

        $this->updateComposerScripts();

        if (in_array('Larastan (Dev)', $selectedLabels)) {
            $this->createPhpstanNeon();
            $this->generatePhpstanBaseline();
        }

        $this->setupHusky();

        $this->info("\n✅ Dev toolkit setup complete! You can run 'php artisan dev-toolkit:install' anytime to update.");

        return self::SUCCESS;
    }

    protected function runComposerRequire(array $packages, bool $dev = false): bool
    {
        $command = array_merge(
            ['composer', 'require'],
            $dev ? ['--dev'] : [],
            ['-W', '--no-interaction'],
            $packages
        );

        $process = new Process($command, base_path());
        $process->setTimeout(null);

        $exitCode = $process->run(function (string $type, string $buffer) {
            echo $buffer;
        });

        if ($exitCode !== 0) {
            $this->error('Composer require failed.');

            return false;
        }

        return true;
    }

    protected function runVendorBinary(string $binary, array $arguments = []): bool
    {
        $command = array_merge(
            [PHP_BINARY, base_path('vendor/bin/'.$binary)],
            $arguments
        );

        $process = new Process($command, base_path());
        $process->setTimeout(null);

        $exitCode = $process->run(function (string $type, string $buffer) {
            echo $buffer;
        });

        return $exitCode === 0;
    }

    protected function updateComposerScripts()
    {
        $this->info("\n📝 Updating composer.json scripts...");
        $composerPath = base_path('composer.json');
        
        if (!file_exists($composerPath)) return;

        $composer = json_decode(file_get_contents($composerPath), true);
        $composer['scripts'] = $composer['scripts'] ?? [];

        // Add the custom scripts
        $composer['scripts']['phpstan'] = ["phpstan analyse"];
        $composer['scripts']['ide-helper'] = ["php artisan ide-helper:models --write-mixin --quiet"];

        file_put_contents(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    protected function createPhpstanNeon()
    {
        $neonPath = base_path('phpstan.neon');
        if (!file_exists($neonPath)) {
            $this->info("\n📝 Creating phpstan.neon...");
            $content = <<<EOT
includes:
    - ./vendor/larastan/larastan/extension.neon

parameters:
    tmpDir: storage/phpstan

    scanFiles:
        - _ide_helper_models.php

    paths:
        - app
        - routes
        - database/seeders
        - database/factories

    level: 5

    excludePaths:
        - tests/*
        - bootstrap/*
        - storage/*
        - public/*
        - database/migrations/*

    # Laravel-specific improvements
    inferPrivatePropertyTypeFromConstructor: true
    treatPhpDocTypesAsCertain: false
    parseModelCastsMethod: true

EOT;
            file_put_contents($neonPath, $content);
        }
    }

    protected function generatePhpstanBaseline()
    {
        $baselinePath = base_path('phpstan-baseline.neon');

        if (file_exists($baselinePath)) {
            $this->info('phpstan-baseline.neon already exists, skipping baseline generation.');
            return;
        }

        if (!file_exists(base_path('phpstan.neon'))) {
            $this->warn('phpstan.neon not found, skipping baseline generation.');
            return;
        }

        $this->info("\n📊 Generating phpstan baseline...");

        if (!$this->runVendorBinary('phpstan', [
            'analyse',
            '--generate-baseline',
            'phpstan-baseline.neon',
            '--no-interaction',
        ]) || !file_exists($baselinePath)) {
            $this->warn('Baseline generation did not create phpstan-baseline.neon.');

            return;
        }

        $neonPath = base_path('phpstan.neon');
        $content = file_get_contents($neonPath);

        if (!str_contains($content, 'phpstan-baseline.neon')) {
            $content = str_replace(
                "includes:\n    - ./vendor/larastan/larastan/extension.neon",
                "includes:\n    - phpstan-baseline.neon\n    - ./vendor/larastan/larastan/extension.neon",
                $content
            );
            file_put_contents($neonPath, $content);
        }
    }

    protected function setupHusky()
    {
        $this->info("\n🐕 Setting up NPM Husky...");
        passthru('npm install --save-dev husky');
        
        // Initialize Husky based on standard modern versions
        passthru('npx husky init');

        $hookContent = <<<'EOT'
#!/bin/sh

# Also check if SKIP_HOOKS env variable is set
if [ "$SKIP_HOOKS" = "1" ]; then
    echo "⏭️  Skipping pre-commit hooks (SKIP_HOOKS=1)"
    exit 0
fi

# 3. Run Pint in check mode (fails if formatting needed, no auto-fix)
echo "🔧 Checking code formatting with Pint..."
php vendor/bin/pint --dirty --test || {
    echo "❌ Failed: Code formatting issues found. Run 'php vendor/bin/pint --dirty' to fix."
    exit 1
}

# 2. Generate IDE helper docblocks (mixin mode)
# echo "📝 Generating IDE helper docblocks..."
# php artisan ide-helper:models --write-mixin --quiet

# # Stage the updated _ide_helper_models.php file if it changed
# if ! git diff --quiet _ide_helper_models.php 2>/dev/null; then
#     git add _ide_helper_models.php
# fi

echo "🧐 Running Larastan..."
php vendor/bin/phpstan analyse --no-interaction || {
    echo "❌ Failed: Larastan found issues."
    exit 1
}

echo "✅ Passed: Format check + Larastan"

EOT;

        $huskyDir = base_path('.husky');
        if (!is_dir($huskyDir)) {
            mkdir($huskyDir, 0755, true);
        }

        $hookPath = $huskyDir . '/pre-commit';
        file_put_contents($hookPath, $hookContent);
        
        // Note for Windows users: chmod might not apply directly via PHP in the same way,
        // but Husky typically handles hook execution locally via Node.
        @chmod($hookPath, 0755); 
    }
}