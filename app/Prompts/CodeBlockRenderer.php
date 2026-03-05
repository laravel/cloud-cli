<?php

namespace App\Prompts;

use App\Concerns\DrawsThemeBoxes;
use App\Enums\TimelineSymbol;
use PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor;
use PHP_Parallel_Lint\PhpConsoleHighlighter\Highlighter;

class CodeBlockRenderer extends Renderer
{
    use DrawsThemeBoxes;

    /**
     * Render the text prompt.
     */
    public function __invoke(CodeBlock $prompt): string
    {
        $maxWidth = $prompt->terminal()->cols() - 6;

        $code = $this->mbWordwrap($prompt->code, $maxWidth);

        $code = trim($code);

        if (! str_starts_with($code, '<?php')) {
            $code = '<?php '.PHP_EOL.$code;
        }

        $highlighter = new Highlighter(new ConsoleColor);
        $code = $highlighter->getWholeFile($code);
        $lines = explode(PHP_EOL, $code);

        while ($this->stripEscapeSequences($lines[0]) === '<?php' || trim($lines[0]) === '') {
            array_shift($lines);
        }

        $code = implode(PHP_EOL, $lines);

        return $this->box(
            '',
            $code,
            symbol: TimelineSymbol::GREATER_THAN,
        );
    }
}
