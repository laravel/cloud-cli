<?php

use App\Commands\InstanceUpdate;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function instanceUpdateOptions(array $input): array
{
    $command = app(InstanceUpdate::class);
    $command->setApplication(new Application);

    $input = new ArrayInput($input, $command->getDefinition());

    // The command reads its options off the bound input, which is normally set by
    // the console kernel when the command is actually run.
    (fn () => $this->input = $input)->call($command);
    (fn () => $this->output = new OutputStyle($input, new BufferedOutput))->call($command);

    return $command->options();
}

it('maps the deprecated hibernation options onto the scale to zero options', function () {
    $options = instanceUpdateOptions([
        '--hibernation' => 'true',
        '--hibernation-timeout' => '17',
    ]);

    expect($options['scale-to-zero'])->toBe('true')
        ->and($options['scale-to-zero-timeout'])->toBe('17');
});

it('prefers the scale to zero options when both are passed', function () {
    $options = instanceUpdateOptions([
        '--hibernation' => 'true',
        '--scale-to-zero' => 'false',
    ]);

    expect($options['scale-to-zero'])->toBe('false');
});

it('leaves the scale to zero options alone when no deprecated options are passed', function () {
    $options = instanceUpdateOptions([
        '--scale-to-zero' => 'true',
    ]);

    expect($options['scale-to-zero'])->toBe('true')
        ->and($options['scale-to-zero-timeout'])->toBeNull();
});
