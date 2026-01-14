<?php

namespace App\Prompts;

use App\Enums\Letter;
use App\Support\Animatable;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;

class WeMustShip extends Prompt
{
    use InteractsWithStrings;

    protected $letters = [];

    /**
     * @var Animatable[]
     */
    protected $animations = [];

    protected $animationsCompleted = [];

    public Collection $lines;

    protected int $minWidth = 60;

    public function animate(): void
    {
        $this->minWidth = min($this->minWidth, $this->terminal()->cols() - 6);

        $this->capturePreviousNewLines();

        $message = collect(str_split('WE MUST SHIP'));
        $startBoldAt = strpos($message->implode(''), 'SHIP');

        $this->hideCursor();

        $this->letters = $message
            ->map(fn ($letter) => collect(Letter::cases())->first(fn ($l) => $l->name === $letter)?->value ?? str_repeat(' '.PHP_EOL, 8))
            ->map(fn ($l) => explode(PHP_EOL, $l))
            ->map(function ($lines, $index) use ($startBoldAt) {
                $animation = new Animatable(0, 0, count($lines));
                $animation->toggle();
                $animation->delay($index);
                $this->animations[] = $animation;

                $longest = collect($lines)->max(fn ($line) => mb_strwidth($line)) + 1;

                return collect($lines)->map(function ($line) use ($longest, $startBoldAt, $index) {
                    while (mb_strwidth($line) < $longest) {
                        $line .= ' ';
                    }

                    $tag = $startBoldAt <= $index ? 'info' : 'comment';

                    return "<{$tag}>{$line}</{$tag}>";
                });
            });

        while (count($this->animationsCompleted) < count($this->animations)) {
            $currentLetters = [];

            foreach ($this->animations as $index => $animation) {
                $letter = $this->letters[$index];
                $newLetter = $letter->toArray();

                $newLetter = array_slice($newLetter, 0, $animation->current());

                while (count($newLetter) < count($letter)) {
                    array_unshift($newLetter, str_repeat(' ', mb_strwidth($this->stripEscapeSequences($letter[0]))));
                }

                $currentLetters[] = $newLetter;

                if ($animation->isAtUpperLimit() && ! in_array($index, $this->animationsCompleted)) {
                    $this->animationsCompleted[] = $index;
                }

                $animation->animate();
            }

            $this->lines = collect(array_shift($currentLetters))->zip(...$currentLetters)->map(fn ($l) => $l->implode(''));
            $this->render();
            Sleep::for(CarbonInterval::milliseconds(25));
        }
    }

    /**
     * Render the prompt.
     */
    protected function render(): void
    {
        $this->terminal()->initDimensions();

        $frame = $this->renderTheme();

        if ($frame === $this->prevFrame) {
            return;
        }

        if ($this->state === 'initial') {
            static::output()->write($frame);

            $this->state = 'active';
            $this->prevFrame = $frame;

            return;
        }

        $terminalHeight = $this->terminal()->lines();
        $previousFrameHeight = count(explode(PHP_EOL, $this->prevFrame));
        $renderableLines = array_slice(explode(PHP_EOL, $frame), abs(min(0, $terminalHeight - $previousFrameHeight)));

        $this->moveCursorToColumn(1);
        $this->moveCursorUp(min($terminalHeight, $previousFrameHeight) - 1);
        // $this->eraseDown();
        $this->output()->write(implode(PHP_EOL, $renderableLines));

        $this->prevFrame = $frame;
    }

    public function value(): mixed
    {
        return null;
    }
}
