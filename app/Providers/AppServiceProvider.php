<?php

namespace App\Providers;

use App\Middleware\CommandMiddlewareManager;
use App\Middleware\RequiresAuthToken;
use App\Middleware\SuppressOutputIfJson;
use App\Prompts\Answered;
use App\Prompts\DynamicSpinner;
use App\Prompts\SpinnerRenderer;
use App\Prompts\TextPromptRenderer;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Prompts\Prompt;
use RuntimeException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $renderers = collect(glob(app_path('Prompts/*.php')))
            ->filter(fn ($file) => str_ends_with($file, 'Renderer.php'))
            ->map(fn ($file) => str(basename($file))->replace('.php', '')->toString())
            ->filter(fn ($class) => $class !== 'Renderer')
            ->mapWithKeys(function ($class) {
                $promptClass = str_replace('Renderer', '', $class);
                $promptClass = collect([
                    'App\\Prompts\\'.$promptClass,
                    'Laravel\\Prompts\\'.$promptClass,
                ])->first(fn ($class) => class_exists($class));

                if (! $promptClass) {
                    throw new RuntimeException('Prompt class not found for renderer: '.$class);
                }

                return [$promptClass => 'App\\Prompts\\'.$class];
            });

        $renderers->offsetSet(Answered::class, TextPromptRenderer::class);
        $renderers->offsetSet(DynamicSpinner::class, SpinnerRenderer::class);

        Prompt::addTheme('cloud', $renderers->toArray());
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
        $manager->register(SuppressOutputIfJson::class);

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
        $this->app->singleton(SuppressOutputIfJson::class);
    }
}
