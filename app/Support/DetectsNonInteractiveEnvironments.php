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
}
