<?php

namespace App\Providers;

use App\Middleware\CommandMiddlewareManager;
use App\Middleware\RequiresAuthToken;
use App\Prompts\Answered;
use App\Prompts\ConfirmPromptRenderer;
use App\Prompts\DataList;
use App\Prompts\DataListRenderer;
use App\Prompts\DynamicSpinner;
use App\Prompts\MonitorDeployments;
use App\Prompts\MonitorDeploymentsRenderer;
use App\Prompts\MultiSelectPromptRenderer;
use App\Prompts\NoteRenderer;
use App\Prompts\PasswordPromptRenderer;
use App\Prompts\SelectPromptRenderer;
use App\Prompts\SlideIn;
use App\Prompts\SlideInRenderer;
use App\Prompts\SpinnerRenderer;
use App\Prompts\TableRenderer;
use App\Prompts\TextareaPromptRenderer;
use App\Prompts\TextPromptRenderer;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Note;
use Laravel\Prompts\PasswordPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\Spinner;
use Laravel\Prompts\Table;
use Laravel\Prompts\TextareaPrompt;
use Laravel\Prompts\TextPrompt;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
            SlideIn::class => SlideInRenderer::class,
            TextPrompt::class => TextPromptRenderer::class,
            Answered::class => TextPromptRenderer::class,
            SelectPrompt::class => SelectPromptRenderer::class,
            MultiSelectPrompt::class => MultiSelectPromptRenderer::class,
            PasswordPrompt::class => PasswordPromptRenderer::class,
            ConfirmPrompt::class => ConfirmPromptRenderer::class,
            TextareaPrompt::class => TextareaPromptRenderer::class,
            MonitorDeployments::class => MonitorDeploymentsRenderer::class,
            DataList::class => DataListRenderer::class,
            Table::class => TableRenderer::class,
        ]);

        Prompt::theme('cloud');

        $this->registerCommandMiddleware();
    }

    /**
     * Register command middleware.
     */
    protected function registerCommandMiddleware(): void
    {
        $manager = $this->app->make(CommandMiddlewareManager::class);

        $manager->register(RequiresAuthToken::class);

        if ($this->app->bound(EventDispatcherInterface::class)) {
            $dispatcher = $this->app->make(EventDispatcherInterface::class);
            $dispatcher->addListener(ConsoleEvents::COMMAND, function ($event) use ($manager) {
                $manager->handleConsoleCommand($event);
            });
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event) use ($manager) {
            $manager->handleCommandStarting($event);
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CommandMiddlewareManager::class);
    }
}
