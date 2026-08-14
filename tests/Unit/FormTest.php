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
