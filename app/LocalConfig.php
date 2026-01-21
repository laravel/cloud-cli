<?php

namespace App;

use Illuminate\Support\Facades\File;

use function Illuminate\Filesystem\join_paths;

class LocalConfig
{
    protected string $configPath;

    public function __construct(Git $git)
    {
        if ($git->isRepo() && $root = $git->getRoot()) {
            $this->configPath = join_paths($root, '.cloud', 'config.json');
        } else {
            $this->configPath = join_paths(getcwd(), '.cloud', 'config.json');
        }
    }

    public function path(): string
    {
        return $this->configPath;
    }

    public function getConfig(): array
    {
        if (! File::exists($this->configPath)) {
            return [];
        }

        return File::json($this->configPath);
    }

    public function get($key, $default = null): mixed
    {
        return $this->getConfig()[$key] ?? $default;
    }

    public function setMany(array $values): void
    {
        $config = $this->getConfig();
        $config = array_merge($config, $values);

        File::put(
            $this->configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    public function set($key, $value): void
    {
        $this->setMany([$key => $value]);
    }
}
