<?php

use App\Prompts\Renderer;
use App\Prompts\TaskRenderer;
use Laravel\Prompts\Task;

// Commands run earlier in the suite leave output suppressed, which blanks every frame.
beforeEach(fn () => Renderer::$suppressOutput = false);

/**
 * The rendered frame with its colour codes stripped, so assertions can read the text.
 */
function renderTask(string $label, int $count = 0, bool $static = false, ?callable $configure = null): string
{
    $task = new Task($label);
    $task->count = $count;
    $task->static = $static;

    if ($configure) {
        $configure($task);
    }

    return preg_replace('/\e\[[0-9;]*m/', '', (new TaskRenderer($task))($task));
}

/**
 * The rendered frame as lines, with the timeline borders and padding stripped off.
 *
 * @return array<int, string>
 */
function taskLines(?callable $configure = null, string $label = 'Creating application'): array
{
    $frame = renderTask($label, configure: $configure);

    return collect(explode(PHP_EOL, $frame))
        ->map(fn (string $line) => trim(str_replace('│', '', $line)))
        ->filter(fn (string $line) => $line !== '' && $line !== '╰')
        ->values()
        ->all();
}

it('cycles a trailing ellipsis while the task runs', function () {
    expect(renderTask('Creating application', 0))->toContain('Creating application')
        ->and(renderTask('Creating application', 0))->not->toContain('.')
        ->and(renderTask('Creating application', 10))->toContain('Creating application.')
        ->and(renderTask('Creating application', 20))->toContain('Creating application..')
        ->and(renderTask('Creating application', 30))->toContain('Creating application...')
        ->and(renderTask('Creating application', 50))->not->toContain('.');
});

it('leaves a label that already ends in punctuation alone', function (string $label) {
    foreach ([0, 10, 20, 30] as $count) {
        expect(renderTask($label, $count))->toContain($label)
            ->and(renderTask($label, $count))->not->toContain($label.'.');
    }
})->with(['Running command...', 'Building!']);

it('ignores escape sequences when checking the label for punctuation', function () {
    expect(renderTask("\e[2m00:07\e[22m Deploying!", 10))->not->toContain('Deploying!.');
});

it('renders a single static frame when the task cannot animate', function () {
    expect(renderTask('Creating application', static: true))->toContain('⠶ Creating application');
});

it('renders nothing beyond the label when the task reports nothing', function () {
    expect(taskLines())->toBe(['⠂  Creating application']);
});

it('renders a sub-label under the label', function () {
    expect(taskLines(fn (Task $task) => $task->subLabel = 'Waiting for the build'))
        ->toBe(['⠂  Creating application', 'Waiting for the build']);
});

it('renders reported messages with a symbol for each type', function () {
    $lines = taskLines(function (Task $task) {
        $task->stableMessages = [
            ['type' => 'success', 'message' => 'Repository cloned'],
            ['type' => 'warning', 'message' => 'No build cache'],
            ['type' => 'error', 'message' => 'Assets failed'],
        ];
    });

    expect($lines)->toBe([
        '⠂  Creating application',
        '✔  Repository cloned',
        '▲  No build cache',
        '✘  Assets failed',
    ]);
});

it('renders logged output and holds the window open at its full height', function () {
    $logging = function (Task $task) {
        $task->limit = 5;
        $task->logs = ['npm install', 'vite build'];
    };

    expect(taskLines($logging))->toBe(['⠂  Creating application', 'npm install', 'vite build']);

    // Label, blank spacer, two logs, three lines of padding, plus the timeline borders.
    $frame = rtrim(renderTask('Creating application', configure: $logging), PHP_EOL);

    expect(substr_count($frame, PHP_EOL))->toBe(8);
});

it('closes the timeline when a finished task keeps its summary', function () {
    $finished = function (Task $task) {
        $task->finished = true;
        $task->stableMessages = [['type' => 'success', 'message' => 'Application created']];
    };

    expect(taskLines($finished))->toBe(['•  Creating application', '✔  Application created'])
        ->and(renderTask('Creating application', configure: $finished))->not->toContain('╰');
});
