<?php

namespace App\SourceProviders;

use App\Enums\SourceProvider;

/**
 * Everything a provider must be able to do. A provider with no CLI of its own
 * implements this and nothing else.
 */
interface SourceProviderDriver
{
    public function provider(): SourceProvider;

    public function baseUrl(): string;

    public function repositoryUrl(string $repository): string;

    public function commitUrl(string $repository, string $commitHash): string;

    public function branchUrl(string $repository, string $branchName): string;
}
