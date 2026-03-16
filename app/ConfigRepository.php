<?php

namespace App;

use Illuminate\Support\Collection;

use function Illuminate\Filesystem\join_paths;

class ConfigRepository
{
    protected string $configPath;

    protected array $config = [];

    public function __construct()
    {
        $filename = 'config.json';

        if (config('app.has_custom_base_url')) {
            $filename = str_replace('.', '-', parse_url(config('app.base_url'))['host']).'-config.json';
        }

        $this->configPath = join_paths($this->getConfigDirectory(), $filename);

        $this->load();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get all API tokens as plain strings (backwards-compatible).
     *
     * Handles both legacy format (plain strings) and new format (associative arrays
     * with 'token', 'organization_name', and optionally 'organization_id' keys).
     *
     * @return Collection<int, string>
     */
    public function apiTokens(): Collection
    {
        return $this->apiTokenEntries()->map(fn (array $entry) => $entry['token'])->unique()->values();
    }

    /**
     * Get all API token entries with their metadata.
     *
     * Each entry is an associative array with keys: token, organization_name, and optionally organization_id.
     * Legacy plain-string tokens are normalized to this format with empty metadata.
     *
     * @return Collection<int, array{token: string, organization_name: string, organization_id?: string}>
     */
    public function apiTokenEntries(): Collection
    {
        return collect($this->get('api_tokens', []))->map(function ($entry) {
            // Backwards compatibility: plain string tokens become arrays
            if (is_string($entry)) {
                return [
                    'token' => $entry,
                    'organization_name' => '',
                    'organization_id' => '',
                ];
            }

            return [
                'token' => $entry['token'] ?? '',
                'organization_name' => $entry['organization_name'] ?? '',
                'organization_id' => $entry['organization_id'] ?? '',
            ];
        })->unique('token')->values();
    }

    public function addApiToken(string $token, string $organizationName = '', string $organizationId = ''): void
    {
        $entries = $this->apiTokenEntries()->reject(fn (array $e) => $e['token'] === $token)->push([
            'token' => $token,
            'organization_name' => $organizationName,
            'organization_id' => $organizationId,
        ])->unique('token')->values();

        $this->config['api_tokens'] = $entries->toArray();
        $this->save();
    }

    public function removeApiToken(string $token): void
    {
        $entries = $this->apiTokenEntries()->reject(fn (array $e) => $e['token'] === $token)->values();

        $this->config['api_tokens'] = $entries->toArray();
        $this->save();
    }

    /**
     * Replace all stored API tokens with the given set.
     *
     * Accepts either a collection of plain strings (backwards-compatible)
     * or a collection of associative arrays with token metadata.
     *
     * @param  Collection<int, string|array>  $tokens
     */
    public function setApiTokens(Collection $tokens): void
    {
        $entries = $tokens->map(function ($entry) {
            if (is_string($entry)) {
                return [
                    'token' => $entry,
                    'organization_name' => '',
                    'organization_id' => '',
                ];
            }

            return [
                'token' => $entry['token'] ?? '',
                'organization_name' => $entry['organization_name'] ?? '',
                'organization_id' => $entry['organization_id'] ?? '',
            ];
        })->unique('token')->values();

        $this->config['api_tokens'] = $entries->toArray();
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
