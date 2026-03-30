<?php

namespace App\Support;

use Exception;

/**
 * @see https://www.asciiart.eu/text-to-ascii-art
 *
 * 3x5
 * amc 3 line
 * thick
 * efti font
 * small
 * 4max
 * basic
 */
class Font
{
    protected static array $baseCharacters = [
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'I',
        'J',
        'K',
        'L',
        'M',
        'N',
        'O',
        'P',
        'Q',
        'R',
        'S',
        'T',
        'U',
        'V',
        'W',
        'X',
        'Y',
        'Z',
        '!',
        '?',
        ':',
    ];

    public function __construct(public array $characters)
    {
        //
    }

    public static function load(string $name): self
    {
        $class = 'App\Fonts\\'.ucfirst($name);

        if (! class_exists($class)) {
            throw new Exception("Font class not found: {$class}");
        }

        $instance = new $class;
        $contents = $instance->characters();
        $lines = collect(explode(PHP_EOL, $contents));

        $height = $lines->search(fn ($line) => trim($line) === '');
        $index = $height;

        while (trim($lines->get($index)) === '') {
            $index++;
        }

        $height = $index - 1;

        $characters = $lines->chunk($height + 1)
            ->map(fn ($chunk) => $chunk->count() === $height + 1 ? $chunk->take($height) : $chunk)
            ->map(function ($chunk) {
                $longest = collect($chunk)->max(fn ($line) => mb_strwidth($line)) + 1;

                return collect($chunk)->map(function ($line) use ($longest) {
                    while (mb_strwidth($line) < $longest) {
                        $line .= ' ';
                    }

                    return $line;
                })->values()->toArray();
            });

        $mappedCharacters = collect(self::$baseCharacters)->mapWithKeys(fn ($character) => [$character => $characters->shift()]);
        $mappedCharacters->offsetSet(' ', array_fill(0, $height, str_repeat(' ', 2)));

        return new self($mappedCharacters->toArray());
    }

    public function characterWidth(): int
    {
        return mb_strwidth($this->characters['A'][0]);
    }

    public function message(string $message): array
    {
        return collect(str_split($message))
            ->map(fn ($character) => $this->characters[$character] ?? null)
            ->toArray();
    }
}
