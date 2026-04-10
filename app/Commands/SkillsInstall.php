<?php

namespace App\Commands;

use App\Contracts\NoAuthRequired;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class SkillsInstall extends BaseCommand implements NoAuthRequired
{
    protected $signature = 'skills:install
                            {--global : Install skills globally}
                            {--project : Install skills to the current project}
                            {--force : Overwrite existing skills}';

    protected $description = 'Install Laravel Cloud CLI agent skills for all supported coding agents';

    protected string $repo = 'laravel/agent-skills';

    protected string $repoPath = 'laravel-cloud/skills';

    /** @var array<int, string> */
    protected array $globalSkillPaths = [
        '~/.claude/skills',
        '~/.cursor/skills',
        '~/.agents/skills',
    ];

    /** @var array<int, string> */
    protected array $projectSkillPaths = [
        '.claude/skills',
        '.cursor/skills',
        '.agents/skills',
        '.github/skills',
        '.junie/skills',
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

                    File::ensureDirectoryExists(dirname($filePath));
                    File::put($filePath, $content);

                    success("Installed skill '{$skillName}' to {$filePath}");
                }

                $skillInstalled = true;
            }

            if ($skillInstalled) {
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

        if ($isProject) {
            return array_map(fn (string $path) => $cwd.'/'.$path, $this->projectSkillPaths);
        }

        return array_map(fn (string $path) => str_replace('~', $home, $path), $this->globalSkillPaths);
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
