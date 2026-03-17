<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir().'/cloud-cli-test-'.uniqid();
    mkdir($this->testDir, 0755, true);
    chdir($this->testDir);
});

afterEach(function () {
    if (is_dir($this->testDir)) {
        // Clean up
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->testDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($this->testDir);
    }
});

it('creates a github actions workflow file', function () {
    $this->artisan('ci:setup')
        ->assertSuccessful();

    $workflowFile = $this->testDir.'/.github/workflows/cloud-deploy.yml';
    expect(file_exists($workflowFile))->toBeTrue();

    $content = file_get_contents($workflowFile);
    expect($content)->toContain('Deploy to Laravel Cloud');
    expect($content)->toContain('LARAVEL_CLOUD_API_TOKEN');
    expect($content)->toContain('cloud deploy --no-interaction');
    expect($content)->toContain('- main');
});

it('creates workflow with custom branch', function () {
    $this->artisan('ci:setup', ['--branch' => 'develop'])
        ->assertSuccessful();

    $workflowFile = $this->testDir.'/.github/workflows/cloud-deploy.yml';
    $content = file_get_contents($workflowFile);
    expect($content)->toContain('- develop');
});

it('fails if workflow file already exists without force flag', function () {
    // Create the file first
    mkdir($this->testDir.'/.github/workflows', 0755, true);
    file_put_contents($this->testDir.'/.github/workflows/cloud-deploy.yml', 'existing');

    $this->artisan('ci:setup')
        ->assertFailed();
});

it('overwrites existing file with force flag', function () {
    // Create the file first
    mkdir($this->testDir.'/.github/workflows', 0755, true);
    file_put_contents($this->testDir.'/.github/workflows/cloud-deploy.yml', 'existing');

    $this->artisan('ci:setup', ['--force' => true])
        ->assertSuccessful();

    $content = file_get_contents($this->testDir.'/.github/workflows/cloud-deploy.yml');
    expect($content)->toContain('Deploy to Laravel Cloud');
});

it('rejects unsupported providers', function () {
    $this->artisan('ci:setup', ['--provider' => 'gitlab'])
        ->assertFailed();
});
