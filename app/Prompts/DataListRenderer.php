<?php

namespace App\Prompts;

use App\Concerns\CapturesOutput;
use App\Concerns\DrawsThemeBoxes;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;

class DataListRenderer extends Renderer
{
    use CapturesOutput;
    use DrawsThemeBoxes;
    use InteractsWithStrings;

    /**
     * Render the data list.
     */
    public function __invoke(DataList $prompt): string
    {
        $first = true;

        foreach ($prompt->data as $key => $value) {
            if ($first) {
                $this->bullet($this->dim($key));
                $first = false;
            } else {
                $this->lineWithBorder('');
                $this->lineWithBorder($this->dim($key));
            }

            $value = match (true) {
                is_array($value) => $value,
                $value === null, trim($value) === '' => ['—'],
                default => explode(PHP_EOL, $value),
            };

            $value = implode(PHP_EOL, $value);
            $value = $this->mbWordwrap($value, $prompt->terminal()->cols() - 6, cut_long_words: true);
            $value = rtrim($value, PHP_EOL);
            $value = explode(PHP_EOL, $value);

            foreach ($value as $item) {
                $this->lineWithBorder($this->green(trim($item)));
            }
        }

        return $this;
    }
}
