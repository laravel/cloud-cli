<?php

namespace App\Prompts;

use App\Dto\Deployment;
use App\Dto\Environment;
use App\Exceptions\CommandExitException;
use App\Support\KeyPressListener;
use Carbon\CarbonImmutable;
use Closure;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use RuntimeException;
use Throwable;

class MonitorDeployments extends Prompt
{
    use Colors;
    use InteractsWithStrings;

    /**
     * How long to wait between rendering each frame.
     */
    public int $interval = 75;

    public int $ellipsisCount = 0;

    public int $autoExit = 5;

    public ?CarbonImmutable $exitTime = null;

    /**
     * How often to check for new deployments in seconds.
     */
    public int $checkEvery = 10;

    public ?CarbonImmutable $lastCheck = null;

    public ?CarbonImmutable $autoExitAt = null;

    /**
     * The number of times the spinner has been rendered.
     */
    public int $count = 0;

    /**
     * Whether the spinner can only be rendered once.
     */
    public bool $static = false;

    /**
     * The process ID after forking.
     */
    protected int $pid;

    /**
     * Socket pair for IPC between parent and child processes.
     *
     * @var resource[]|null
     */
    protected ?array $sockets = null;

    public string $lastMessage = '';

    public string $displayMessage = '';

    protected string $resetIdentifier = '';

    public ?Deployment $deployment = null;

    public ?Deployment $lastDeployment = null;

    public bool $fade = true;

    /**
     * Create a new Spinner instance.
     */
    public function __construct(public Closure $getDeployment, public ?Environment $environment = null)
    {
        $this->resetIdentifier = str()->random(10);
        $this->autoExitAt = CarbonImmutable::now()->addMinutes($this->autoExit);
    }

    /**
     * Render the deployment monitor loop.
     */
    public function display(): void
    {
        $this->capturePreviousNewLines();
        $this->hideCursor();

        try {
            static::terminal()->setTty('-icanon -isig -echo');
        } catch (Throwable $e) {
            //
        }

        $keyPressListener = KeyPressListener::for($this)->listenForQuit()->on('o', function () {
            if ($this->lastDeployment) {
                openUrl($this->environment->url);
            }
        });

        while (true) {
            $this->render();

            if (! $this->deployment && $this->autoExitAt && CarbonImmutable::now()->isAfter($this->autoExitAt)) {
                throw new CommandExitException(0);
            }

            $this->count++;

            if ($this->count % 10 === 0) {
                $this->ellipsisCount++;
            }

            if ($this->deployment) {
                $this->checkEvery = 3;
            } else {
                $this->checkEvery = 10;
            }

            if ($this->lastCheck === null || $this->lastCheck->diffInSeconds(CarbonImmutable::now()) > $this->checkEvery) {
                $this->lastCheck = CarbonImmutable::now();
                $this->deployment = ($this->getDeployment)($this->deployment?->id);

                if ($this->deployment && $this->deployment->isFinished()) {
                    $this->lastDeployment = $this->deployment;
                    $this->deployment = null;
                }
            }

            $keyPressListener->once();

            usleep($this->interval * 1000);
        }
    }

    // /**
    //  * Render the spinner.
    //  */
    // protected function render(): void
    // {
    //     try {
    //         $this->hideCursor();
    //         $this->render();

    //         $result = $callback($noopUpdater);
    //     } finally {
    //         $this->eraseRenderedLines();
    //     }

    //     return $result;
    // }

    /**
     * Disable prompting for input.
     *
     * @throws RuntimeException
     */
    public function prompt(): never
    {
        throw new RuntimeException('Spinner cannot be prompted.');
    }

    /**
     * Get the current value of the prompt.
     */
    public function value(): bool
    {
        return true;
    }

    /**
     * Clear the lines rendered by the spinner.
     */
    protected function eraseRenderedLines(): void
    {
        $lines = explode(PHP_EOL, $this->prevFrame);
        $this->moveCursor(-999, -count($lines) + 1);
        $this->eraseDown();
    }
}
