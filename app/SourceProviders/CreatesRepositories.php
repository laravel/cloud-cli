<?php

namespace App\SourceProviders;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Collection;

/**
 * Implemented only by providers that ship a CLI we can create a repository with.
 */
interface CreatesRepositories extends SourceProviderDriver
{
    public function cliName(): string;

    public function cliInstalled(): bool;

    public function cliAuthenticated(): bool;

    /**
     * Accounts and organisations the authenticated user can create a repository under.
     *
     * @return Collection<string, string>
     */
    public function owners(): Collection;

    public function createRepository(string $name, string $owner, bool $private): ProcessResult;
}
