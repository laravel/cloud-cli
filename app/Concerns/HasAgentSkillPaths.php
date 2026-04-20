<?php

namespace App\Concerns;

use Illuminate\Support\Facades\File;

trait HasAgentSkillPaths
{
    /** @var array<string, array{global: string, project: string}> */
    protected array $agents = [
        'claude' => ['global' => '~/.claude/skills', 'project' => '.claude/skills'],
        'cursor' => ['global' => '~/.cursor/skills', 'project' => '.cursor/skills'],
        'junie' => ['global' => '~/.junie/skills', 'project' => '.junie/skills'],
        'github' => ['global' => '~/.github/skills', 'project' => '.github/skills'],
        'agents' => ['global' => '~/.agents/skills', 'project' => '.agents/skills'],
    ];

    protected function resolveAgentSkillPath(string $agent, string $scope = 'global'): string
    {
        $path = $this->agents[$agent][$scope];

        if ($scope === 'project') {
            return getcwd().'/'.$path;
        }

        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';

        return str_replace('~', $home, $path);
    }

    /**
     * Agents whose parent directory exists on disk for the given scope.
     *
     * @return array<int, string>
     */
    protected function detectAgents(string $scope = 'global'): array
    {
        $detected = [];

        foreach (array_keys($this->agents) as $agent) {
            $parent = dirname($this->resolveAgentSkillPath($agent, $scope));

            if (File::isDirectory($parent)) {
                $detected[] = $agent;
            }
        }

        return $detected;
    }
}
