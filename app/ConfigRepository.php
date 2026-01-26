<?php

namespace App;

use Illuminate\Support\Collection;

class ConfigRepository
{
    protected string $configPath;

    protected array $config = [];

    public function __construct()
    {
        $this->configPath = $this->getConfigDirectory().'/config.json';
        $this->load();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * @return Collection<string>
     */
    public function apiTokens(): Collection
    {
        return collect($this->get('api_tokens', []));
    }

    public function addApiToken(string $token): void
    {
        $this->config['api_tokens'] = $this->apiTokens()->push($token);
        $this->save();
    }

    public function removeApiToken(string $token): void
    {
        $this->config['api_tokens'] = $this->apiTokens()->reject(fn ($t) => $t === $token);
        $this->save();
    }

    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
        $this->save();
    }

    public function path(): string
    {
        return $this->configPath;
    }

    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    protected function getConfigDirectory(): string
    {
        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: posix_getpwuid(posix_getuid())['dir']);

        return $home.'/.config/cloud';
    }

    protected function load(): void
    {
        if (file_exists($this->configPath)) {
            $contents = file_get_contents($this->configPath);
            $this->config = json_decode($contents, true) ?? [];
        }
    }

    protected function save(): void
    {
        $directory = dirname($this->configPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $this->configPath,
            json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
