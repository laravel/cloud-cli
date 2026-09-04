<?php

namespace App;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class Git
{
    public function isRepo(): bool
    {
        return $this->run(['git', 'rev-parse', '--is-inside-work-tree'])->successful();
    }

    public function getRoot(): ?string
    {
        $result = $this->run(['git', 'rev-parse', '--show-toplevel']);

        if (! $result->successful()) {
            return null;
        }

        return trim($result->output());
    }

    public function hasRemote(): bool
    {
        return $this->remoteUrl() !== null;
    }

    public function initRepo(): bool
    {
        return $this->run(['git', 'init'])->successful();
    }

    public function addRemote(string $name, string $url): bool
    {
        return $this->run(['git', 'remote', 'add', $name, $url])->successful();
    }

    public function currentDirectoryName(): string
    {
        return basename(getcwd());
    }

    public function remoteRepo(): string
    {
        $url = $this->remoteUrl();

        if ($url === null) {
            return '';
        }

        $path = Str::of($url)->contains('://')
            ? Str::of($url)->after('://')->after('/')
            : Str::of($url)->after(':');

        return $path->beforeLast('.git')->toString();
    }

    /**
     * The host the origin remote points at, which is how we tell one provider from another.
     */
    public function remoteHost(): ?string
    {
        $url = $this->remoteUrl();

        if ($url === null) {
            return null;
        }

        $host = Str::of($url)->contains('://')
            ? Str::of($url)->after('://')->after('@')->before('/')
            : Str::of($url)->before(':')->afterLast('@');

        // Ports are not part of the host we match on.
        $host = $host->before(':')->lower()->toString();

        return $host === '' ? null : $host;
    }

    public function addAll(): bool
    {
        return $this->run(['git', 'add', '-A'])->successful();
    }

    public function commit(string $message): ProcessResult
    {
        return $this->run(['git', 'commit', '-m', $message]);
    }

    public function push(): ProcessResult
    {
        return $this->run(['git', 'push', '-u', 'origin', 'HEAD']);
    }

    public function getDefaultBranch(): string
    {
        $result = $this->run(['git', 'symbolic-ref', 'refs/remotes/origin/HEAD']);

        if ($result->successful()) {
            return str($result->output())->trim()->afterLast('/')->toString();
        }

        $result = $this->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);

        if ($result->successful()) {
            return trim($result->output());
        }

        return 'main';
    }

    public function currentBranch(): string
    {
        $result = $this->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);

        if ($result->successful()) {
            return trim($result->output());
        }

        return $this->getDefaultBranch();
    }

    protected function remoteUrl(): ?string
    {
        $result = $this->run(['git', 'remote', 'get-url', 'origin']);

        if (! $result->successful()) {
            return null;
        }

        $url = trim($result->output());

        return $url === '' ? null : $url;
    }

    protected function run(array $command): ProcessResult
    {
        return Process::run($command);
    }
}
