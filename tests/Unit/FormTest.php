<?php

use App\Dto\ValidationErrors;
use App\Support\Form;

it('prompts without errors having been set', function () {
    $form = (new Form)
        ->isInteractive(false)
        ->options([])
        ->arguments(['name' => 'my-restore']);

    $form->prompt('name', fn ($resolver) => $resolver);

    expect($form->get('name'))->toBe('my-restore');
});

it('reads booleans from string options', function (string $option, bool $expected) {
    $form = (new Form)
        ->isInteractive(false)
        ->options(['is-public' => $option])
        ->arguments([]);

    $form->prompt('is_public', fn ($resolver) => $resolver);

    expect($form->boolean('is_public'))->toBe($expected);
})->with([
    ['false', false],
    ['0', false],
    ['true', true],
    ['1', true],
]);

it('returns null for a boolean that was never provided', function () {
    $form = (new Form)
        ->isInteractive(false)
        ->options([])
        ->arguments([]);

    expect($form->boolean('is_public'))->toBeNull();
});

it('uses a falsy non-interactive default rather than treating it as missing', function (mixed $default) {
    $form = (new Form)
        ->isInteractive(false)
        ->options([])
        ->arguments([]);

    $form->prompt('uses_scheduler', fn ($resolver) => $resolver->nonInteractively(fn () => $default));

    expect($form->get('uses_scheduler'))->toBe($default);
})->with([false, 0, '']);

it('requires a value when the non-interactive default is null', function () {
    $form = (new Form)
        ->isInteractive(false)
        ->options(['type' => null])
        ->arguments([]);

    expect(fn () => $form->prompt('type', fn ($resolver) => $resolver->nonInteractively(fn () => null)))
        ->toThrow(RuntimeException::class, 'type is required. Provide --type option.');
});

it('keeps errors passed in before prompting', function () {
    $errors = new ValidationErrors;
    $errors->add('name', 'Name has already been taken.');

    $form = (new Form)
        ->isInteractive(false)
        ->options([])
        ->arguments(['name' => 'taken'])
        ->errors($errors);

    $resolver = $form->prompt('name', fn ($resolver) => $resolver);

    expect($resolver->value())->toBe('taken');
});
