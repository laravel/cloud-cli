<?php

namespace App\Middleware;

use App\Commands\SkillsInstall;
use App\Concerns\DetectsInstallScope;
use App\Concerns\HasAgentSkillPaths;
use App\ConfigRepository;
use App\Middleware\Concerns\SkipsInternalCommands;
use App\Support\DetectsNonInteractiveEnvironments;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class OffersSkillsInstall implements CommandMiddleware
{
    use DetectsInstallScope;
    use DetectsNonInteractiveEnvironments;
    use HasAgentSkillPaths;
    use SkipsInternalCommands;

    protected const SKIP_COMMANDS = [
        'skills:install',
        'auth',
        'login',
    ];

    protected const MARKER_SKILL = 'deploying-laravel-cloud';

    public function handle($command, callable $next)
    {
        if ($this->isInternalCommand($command)) {
            return $next();
        }

        $name = is_string($command) ? $command : $command?->getName();

        if (
            in_array($name, self::SKIP_COMMANDS)
            || ! $this->isInteractiveSession()
            || ! $this->isGloballyInstalled()
        ) {
            return $next();
        }

        $detectedAgents = $this->detectAgents();

        if ($detectedAgents === [] || $this->skillsAlreadyInstalled($detectedAgents)) {
            return $next();
        }

        $config = app(ConfigRepository::class);

        if ($config->get('skills_install_prompted_at')) {
            return $next();
        }

        $config->set('skills_install_prompted_at', now()->toIso8601String());

        $install = confirm(
            label: 'Install Laravel Cloud CLI skills for AI agents?',
            default: true,
            hint: 'Adds slash commands/guidance so your agent knows how to use the CLI.',
        );

        if ($install) {
            $this->runInstall();
        } else {
            $this->showDeclineHint();
        }

        return $next();
    }

    protected function runInstall(): void
    {
        Artisan::call(SkillsInstall::class);
    }

    protected function showDeclineHint(): void
    {
        info('You can install them later with: <comment>cloud skills:install</comment>');
    }

    /**
     * @param  array<int, string>  $agents
     */
    protected function skillsAlreadyInstalled(array $agents): bool
    {
        foreach ($agents as $agent) {
            $skillsPath = $this->resolveAgentSkillPath($agent);

            if (File::isDirectory($skillsPath.'/'.self::MARKER_SKILL)) {
                return true;
            }
        }

        return false;
    }
}
