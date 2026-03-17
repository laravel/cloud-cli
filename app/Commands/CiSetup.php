<?php

namespace App\Commands;

use App\Contracts\NoAuthRequired;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class CiSetup extends BaseCommand implements NoAuthRequired
{
    protected $signature = 'ci:setup
                            {--provider=github-actions : CI provider}
                            {--branch=main : Branch to deploy from}
                            {--force : Overwrite existing workflow file}';

    protected $description = 'Generate CI/CD workflow configuration for deploying to Laravel Cloud';

    public function handle()
    {
        intro('CI/CD Setup');

        $provider = $this->option('provider');
        $branch = $this->option('branch');

        if ($provider !== 'github-actions') {
            error("Unsupported CI provider: {$provider}. Currently only 'github-actions' is supported.");

            return self::FAILURE;
        }

        return $this->setupGitHubActions($branch);
    }

    protected function setupGitHubActions(string $branch): int
    {
        $workflowDir = getcwd().'/.github/workflows';
        $workflowFile = $workflowDir.'/cloud-deploy.yml';

        if (file_exists($workflowFile) && ! $this->option('force')) {
            warning('Workflow file already exists: .github/workflows/cloud-deploy.yml');
            info('Use --force to overwrite the existing file.');

            return self::FAILURE;
        }

        if (! is_dir($workflowDir)) {
            mkdir($workflowDir, 0755, true);
        }

        $template = $this->getGitHubActionsTemplate($branch);

        file_put_contents($workflowFile, $template);

        info('Created .github/workflows/cloud-deploy.yml');

        $this->line('');
        intro('Next Steps');
        $this->line('  1. Add your <comment>LARAVEL_CLOUD_API_TOKEN</comment> as a GitHub repository secret');
        $this->line('     Go to: Settings > Secrets and variables > Actions > New repository secret');
        $this->line('');
        $this->line('  2. Commit and push the workflow file:');
        $this->line('     <comment>git add .github/workflows/cloud-deploy.yml</comment>');
        $this->line('     <comment>git commit -m "Add Laravel Cloud deploy workflow"</comment>');
        $this->line('     <comment>git push</comment>');
        $this->line('');
        $this->line("  3. Push to <comment>{$branch}</comment> to trigger a deployment");

        return self::SUCCESS;
    }

    protected function getGitHubActionsTemplate(string $branch): string
    {
        return <<<YAML
name: Deploy to Laravel Cloud

on:
  push:
    branches:
      - {$branch}

jobs:
  deploy:
    name: Deploy
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          coverage: none

      - name: Install Laravel Cloud CLI
        run: composer global require laravel/cloud-cli

      - name: Deploy to Laravel Cloud
        env:
          LARAVEL_CLOUD_API_TOKEN: \${{ secrets.LARAVEL_CLOUD_API_TOKEN }}
        run: cloud deploy --no-interaction

YAML;
    }
}
