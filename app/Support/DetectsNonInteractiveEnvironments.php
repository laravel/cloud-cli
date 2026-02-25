<?php

namespace App\Support;

trait DetectsNonInteractiveEnvironments
{
    protected function isNonInteractiveEnvironment(): bool
    {
        $envs = [
            'CI',
            'CURSOR',
            'GITHUB_ACTIONS',
            'GITLAB_CI',
            'JENKINS_URL',
            'CIRCLECI',
            'TRAVIS',
            'AGENT_MODE',
        ];

        foreach ($envs as $env) {
            if (! empty(getenv($env))) {
                return true;
            }
        }

        return false;
    }
}
