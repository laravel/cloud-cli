<?php

namespace App\SourceProviders;

use App\Enums\SourceProvider;

class GitLabSelfHostedProvider extends GitLabProvider
{
    public function __construct(protected string $host)
    {
        //
    }

    public function provider(): SourceProvider
    {
        return SourceProvider::GITLAB_SELF_HOSTED;
    }

    public function baseUrl(): string
    {
        return 'https://'.$this->host;
    }

    /**
     * `glab repo create` takes no --hostname, so the host goes in the project path.
     */
    protected function projectPath(string $owner, string $name): string
    {
        return $this->host.'/'.$owner.'/'.$name;
    }

    protected function hostArgs(): array
    {
        return ['--hostname', $this->host];
    }
}
