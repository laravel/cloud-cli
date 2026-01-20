<?php

namespace App\Prompts;

class DataListRenderer extends Renderer
{
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
                $this->lineWithBorder($this->dim($key));
            }

            $value = is_array($value) ? $value : [$value];

            foreach ($value as $item) {
                $this->lineWithBorder($this->green($item));
            }

            $this->lineWithBorder('');
        }

        return $this;
    }
}
