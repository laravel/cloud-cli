<?php

namespace App\Providers;

use App\Prompts\DynamicSpinner;
use App\Prompts\NoteRenderer;
use App\Prompts\SpinnerRenderer;
use Illuminate\Support\ServiceProvider;
use Laravel\Prompts\Note;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Spinner;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Prompt::addTheme('cloud', [
            DynamicSpinner::class => SpinnerRenderer::class,
            Note::class => NoteRenderer::class,
            Spinner::class => SpinnerRenderer::class,
        ]);

        Prompt::theme('cloud');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
