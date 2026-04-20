<?php

namespace App\Concerns;

use Illuminate\Support\Facades\File;

trait DetectsInstallScope
{
    /**
     * Whether the CLI is installed globally (as opposed to being
     * required as a project-level dependency in the current directory).
     */
    protected function isGloballyInstalled(): bool
    {
        $cwd = getcwd();

        if ($cwd === false) {
            return true;
        }

        return ! File::isDirectory($cwd.'/vendor/laravel/cloud-cli');
    }
}
