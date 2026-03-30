<?php

namespace App\Prompts;

use App\Support\Animatable;
use App\Support\Font;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;

class SlideIn extends Prompt
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

    public function __construct(public string $message)
    {
        //
    }

    public function animate(): void
    {
        if (Renderer::$suppressOutput) {
            return;
        }

        $this->minWidth = min($this->minWidth, $this->terminal()->cols() - 6);

        $font = Font::load('computer');

        $this->capturePreviousNewLines();

        preg_match_all('/\*/', $this->message, $matches, PREG_OFFSET_CAPTURE);
        $boldIndexes = collect($matches[0])->pluck(1)->toArray();
        $bold = false;

        $this->hideCursor();

        $this->letters = collect($font->message(str_replace('*', '', $this->message)))
            ->map(function ($lines, $index) use ($boldIndexes, &$bold) {
                $animation = new Animatable(0, 0, count($lines));
                $animation->toggle();
                $animation->delay($index);
                $this->animations[] = $animation;

                $longest = collect($lines)->max(fn ($line) => mb_strwidth($line)) + 1;

                if (in_array($index, $boldIndexes)) {
                    $bold = ! $bold;
                }

                return collect($lines)->map(function ($line) use ($longest, $bold) {
                    while (mb_strwidth($line) < $longest) {
                        $line .= ' ';
                    }

                    $tag = $bold ? 'info' : 'comment';

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
