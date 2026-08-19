<?php

namespace Tests\Fixtures;

use App\Commands\BaseCommand;

use function Laravel\Prompts\autocomplete;
use function Laravel\Prompts\select;

class PromptFallbackTestCommand extends BaseCommand
{
    protected $signature = 'test:prompt-fallback';

    public function handle(): void
    {
        $application = select(
            label: 'Application',
            options: ['app-1' => 'First app', 'app-2' => 'Second app'],
        );

        $command = autocomplete(
            label: 'Command',
            options: ['php artisan migrate', 'php artisan queue:work'],
        );

        $this->line('selected:'.$application);
        $this->line('typed:'.$command);
    }
}
