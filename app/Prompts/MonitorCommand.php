<?php

namespace App\Prompts;

use App\Dto\Command;
use App\Support\KeyPressListener;
use Carbon\CarbonImmutable;
use Closure;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use RuntimeException;
use Throwable;

class MonitorCommand extends Prompt
{
    use Colors;
    use InteractsWithStrings;

    public int $interval = 75;

    public int $ellipsisCount = 0;

    public int $checkEvery = 3;

    public ?CarbonImmutable $lastCheck = null;

    public int $count = 0;

    protected string $resetIdentifier = '';

    public ?Command $lastCommand = null;

    /**
     * @param  Closure(string): ?Command  $getCommand
     */
    public function __construct(public Closure $getCommand, public ?Command $command)
    {
        $this->resetIdentifier = str()->random(10);
    }

    public function display(): void
    {
        $this->capturePreviousNewLines();
        $this->hideCursor();

        try {
            static::terminal()->setTty('-icanon -isig -echo');
        } catch (Throwable $e) {
            //
        }

        $keyPressListener = KeyPressListener::for($this)->listenForQuit();

        while (true) {
            $this->render();

            $this->count++;

            if ($this->count % 10 === 0) {
                $this->ellipsisCount++;
            }

            if ($this->lastCheck === null || $this->lastCheck->diffInSeconds(CarbonImmutable::now()) >= $this->checkEvery) {
                $this->lastCheck = CarbonImmutable::now();

                if ($updated = ($this->getCommand)($this->command->id)) {
                    $this->command = $updated;

                    if ($this->command->isFinished()) {
                        $this->lastCommand = ($this->getCommand)($this->command->id);
                        $this->command = null;
                        $this->state = 'submit';
                        $this->render();

                        break;
                    }
                }
            }

            $keyPressListener->once();

            usleep($this->interval * 1000);
        }
    }

    public function prompt(): never
    {
        throw new RuntimeException('Monitor Command cannot be prompted.');
    }

    public function value(): bool
    {
        return true;
    }

    protected function eraseRenderedLines(): void
    {
        $lines = explode(PHP_EOL, $this->prevFrame);
        $this->moveCursor(-999, -count($lines) + 1);
        $this->eraseDown();
    }
}
