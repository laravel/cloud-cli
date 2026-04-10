<?php

namespace App\Commands;

use App\Contracts\NoAuthRequired;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class SkillsInstall extends BaseCommand implements NoAuthRequired
{
    protected $signature = 'skills:install
                            {--global : Install skills globally}
                            {--project : Install skills to the current project}
                            {--agent=* : Agents to install for (claude, cursor, junie, github, agents)}
                            {--force : Overwrite existing skills}';

    protected $description = 'Install Laravel Cloud CLI agent skills for all supported coding agents';

    protected string $repo = 'laravel/agent-skills';

    protected string $repoPath = 'laravel-cloud/skills';

    /** @var array<string, array{global: string, project: string}> */
    protected array $agents = [
        'claude' => ['global' => '~/.claude/skills', 'project' => '.claude/skills'],
        'cursor' => ['global' => '~/.cursor/skills', 'project' => '.cursor/skills'],
        'junie' => ['global' => '~/.junie/skills', 'project' => '.junie/skills'],
        'github' => ['global' => '~/.github/skills', 'project' => '.github/skills'],
        'agents' => ['global' => '~/.agents/skills', 'project' => '.agents/skills'],
    ];

    public function handle(): int
    {
        intro('Install Agent Skills');

        $skills = spin(
            fn () => $this->fetchSkills(),
            'Fetching skills from GitHub...',
        );

        if ($skills === []) {
            $this->failAndExit('No skills found in the repository.');
        }

        $skillPaths = $this->resolveSkillPaths();
        $installedSkills = [];
        $skippedSkills = [];

        foreach ($skills as $skillName => $files) {
            $skillInstalled = false;
            $filePaths = [];

            foreach ($skillPaths as $basePath) {
                $targetDir = $basePath.'/'.$skillName;

                if (File::isDirectory($targetDir) && ! $this->option('force')) {
                    continue;
                }

                if (File::isDirectory($targetDir)) {
                    File::deleteDirectory($targetDir);
                }

                foreach ($files as $relativePath => $content) {
                    $filePath = $targetDir.'/'.$relativePath;
                    $filePaths[] = $filePath;

                    File::ensureDirectoryExists(dirname($filePath));
                    File::put($filePath, $content);
                }

                $skillInstalled = true;
            }

            if ($skillInstalled) {
                success("Installed skill <comment>{$skillName}</comment> <info>to:</info>".PHP_EOL.PHP_EOL.implode(PHP_EOL, $filePaths));
                $installedSkills[] = $skillName;
            } else {
                warning("Skill '{$skillName}' already exists in all target locations. Use --force to overwrite.");
                $skippedSkills[] = $skillName;
            }
        }

        $this->outputJsonIfWanted([
            'installed' => $installedSkills,
            'skipped' => $skippedSkills,
        ]);

        if ($installedSkills === [] && $skippedSkills !== []) {
            warning('All skills already installed. Use --force to overwrite.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveSkillPaths(): array
    {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
        $cwd = getcwd();

        $isProject = match (true) {
            $this->option('global') => false,
            $this->option('project') => true,
            default => File::isDirectory($cwd.'/vendor/laravel/cloud-cli'),
        };

        $scope = $isProject ? 'project' : 'global';
        $selectedAgents = $this->resolveAgents($scope, $home, $cwd);

        return array_map(function (string $agent) use ($scope, $home, $cwd) {
            $path = $this->agents[$agent][$scope];

            if ($scope === 'project') {
                return $cwd.'/'.$path;
            }

            return str_replace('~', $home, $path);
        }, $selectedAgents);
    }

    /**
     * @return array<int, string>
     */
    protected function resolveAgents(string $scope, string $home, string $cwd): array
    {
        $explicit = array_filter($this->option('agent'));

        if ($explicit !== []) {
            return array_values(array_intersect($explicit, array_keys($this->agents)));
        }

        $detected = $this->detectAgents($scope, $home, $cwd);

        if ($this->input->isInteractive()) {
            $options = collect($this->agents)->keys()->mapWithKeys(fn (string $agent) => [
                $agent => match ($agent) {
                    'agents' => 'Generic agent skills (for any agent)',
                    default => ucfirst($agent),
                },
            ])->all();

            return multiselect(
                label: 'Which agents do you want to install skills for?',
                options: $options,
                default: $detected ?: array_keys($this->agents),
            );
        }

        return $detected ?: array_keys($this->agents);
    }

    /**
     * @return array<int, string>
     */
    protected function detectAgents(string $scope, string $home, string $cwd): array
    {
        $detected = [];

        foreach ($this->agents as $agent => $paths) {
            $skillPath = $paths[$scope];
            $parentDir = dirname($skillPath);

            if ($scope === 'project') {
                $parentDir = $cwd.'/'.$parentDir;
            } else {
                $parentDir = str_replace('~', $home, $parentDir);
            }

            if (File::isDirectory($parentDir)) {
                $detected[] = $agent;
            }
        }

        return $detected;
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function fetchSkills(): array
    {
        $tree = $this->fetchTree();
        $prefix = $this->repoPath.'/';

        // Find skill directories by locating SKILL.md files
        $skillMarkers = collect($tree)
            ->filter(
                fn (array $item) => $item['type'] === 'blob'
                    && basename($item['path']) === 'SKILL.md'
                    && str_starts_with($item['path'], $prefix),
            );

        if ($skillMarkers->isEmpty()) {
            return [];
        }

        $skills = [];

        foreach ($skillMarkers as $marker) {
            $skillDir = dirname($marker['path']);
            $skillName = basename($skillDir);

            // Collect all files belonging to this skill
            $skillFiles = collect($tree)
                ->filter(
                    fn (array $item) => $item['type'] === 'blob'
                        && str_starts_with($item['path'], $skillDir.'/'),
                );

            $fileUrls = $skillFiles->mapWithKeys(fn (array $item) => [
                $item['path'] => $this->rawUrl($item['path']),
            ]);

            // Download all files in parallel
            $responses = Http::pool(fn (Pool $pool) => $fileUrls->map(
                fn (string $url, string $path) => $pool->as($path)
                    ->withHeaders(['User-Agent' => 'Laravel-Cloud-CLI'])
                    ->timeout(30)
                    ->get($url),
            )->all());

            $downloaded = [];

            foreach ($skillFiles as $file) {
                $response = $responses[$file['path']] ?? null;

                if ($response === null || $response->failed()) {
                    continue;
                }

                $relativePath = substr($file['path'], strlen($skillDir.'/'));
                $downloaded[$relativePath] = $response->body();
            }

            if ($downloaded !== []) {
                $skills[$skillName] = $downloaded;
            }
        }

        return $skills;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchTree(): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Laravel-Cloud-CLI',
        ])->timeout(30)->get(
            "https://api.github.com/repos/{$this->repo}/git/trees/main?recursive=1",
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Failed to fetch repository tree from GitHub: '
                    .($response->json('message') ?? 'Unknown error')
                    ." (HTTP {$response->status()})",
            );
        }

        return $response->json('tree', []);
    }

    protected function rawUrl(string $path): string
    {
        return "https://raw.githubusercontent.com/{$this->repo}/main/{$path}";
    }
}
