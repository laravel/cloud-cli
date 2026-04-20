<?php

namespace App\Support;

use AgentDetector\AgentDetector;

trait DetectsNonInteractiveEnvironments
{
    protected function isAgentEnvironment(): bool
    {
        return AgentDetector::detect()->isAgent;
    }

    protected function isNonInteractiveEnvironment(): bool
    {
        if ($this->isAgentEnvironment()) {
            return true;
        }

        $envs = [
            'CI',
            'GITHUB_ACTIONS',
            'GITLAB_CI',
            'JENKINS_URL',
            'CIRCLECI',
            'TRAVIS',
        ];

        foreach ($envs as $env) {
            if (! empty(getenv($env))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pre-option-parse interactivity check — suitable for middleware where
     * Symfony hasn't bound options yet, so we scan argv directly.
     */
    protected function isInteractiveSession(): bool
    {
        if ($this->isNonInteractiveEnvironment()) {
            return false;
        }

        if (! stream_isatty(STDIN)) {
            return false;
        }

        $args = $_SERVER['argv'] ?? [];

        return collect($args)->intersect(['--json', '--no-interaction', '-n'])->isEmpty();
    }
}
