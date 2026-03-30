<?php

namespace App\Support;

use AgentDetector\AgentDetector;

trait DetectsNonInteractiveEnvironments
{
    protected function isNonInteractiveEnvironment(): bool
    {
        if (AgentDetector::detect()->isAgent) {
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
