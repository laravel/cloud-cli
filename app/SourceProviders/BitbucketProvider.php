<?php

namespace App\SourceProviders;

use App\Enums\SourceProvider;

/**
 * Bitbucket ships no CLI we can create a repository with, so this is URLs only.
 */
class BitbucketProvider implements SourceProviderDriver
{
    public function provider(): SourceProvider
    {
        return SourceProvider::BITBUCKET;
    }

    public function baseUrl(): string
    {
        return 'https://bitbucket.org';
    }

    public function repositoryUrl(string $repository): string
    {
        return $this->baseUrl().'/'.$repository;
    }

    public function commitUrl(string $repository, string $commitHash): string
    {
        return $this->repositoryUrl($repository).'/commits/'.$commitHash;
    }

    public function branchUrl(string $repository, string $branchName): string
    {
        return $this->repositoryUrl($repository).'/branch/'.$branchName;
    }
}
