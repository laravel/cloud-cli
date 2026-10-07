<?php

namespace App\Prompts;

use Closure;
use Laravel\Prompts\TextPrompt;

class SuffixedTextPrompt extends TextPrompt
{
    public function __construct(
        string $label,
        public string $suffix,
        string $placeholder = '',
        string $default = '',
        bool|string $required = false,
        mixed $validate = null,
        string $hint = '',
        ?Closure $transform = null,
    ) {
        parent::__construct($label, $placeholder, $default, $required, $validate, $hint, $transform);
    }
}
