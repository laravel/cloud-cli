<?php

use App\Concerns\Validates;
use App\Dto\ValidationErrors;
use App\Exceptions\CommandExitException;
use App\Support\Form;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

class ValidatesHost
{
    use Validates;

    public int $attempts = 0;

    public function __construct(protected Form $form, protected bool $interactive = true) {}

    public function run(callable $callback, ?callable $shouldRetry = null): mixed
    {
        return $this->loopUntilValid($callback, suppressOutput: true, shouldRetry: $shouldRetry);
    }

    public function form(): Form
    {
        return $this->form;
    }

    protected function isInteractive(): bool
    {
        return $this->interactive;
    }

    protected function wantsJson(): bool
    {
        return false;
    }
}

function validationException(array $errors): RequestException
{
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn(422);
    $response->shouldReceive('json')->with('errors', [])->andReturn($errors);

    return new RequestException($response, 'Unprocessable Content');
}

function validatesHost(bool $interactive = true): ValidatesHost
{
    return new ValidatesHost(
        (new Form)->isInteractive($interactive)->options([])->arguments([]),
        $interactive,
    );
}

it('stops retrying when the same error comes back and no field can be prompted for again', function () {
    $host = validatesHost();

    expect(fn () => $host->run(function () use ($host) {
        $host->attempts++;

        throw validationException(['global' => ['The selected type is not available on your plan.']]);
    }))->toThrow(CommandExitException::class);

    expect($host->attempts)->toBe(2);
});

it('keeps retrying while the callback says the error is worth waiting out', function () {
    $host = validatesHost();

    $host->run(
        function () use ($host) {
            $host->attempts++;

            if ($host->attempts < 4) {
                throw validationException(['global' => ['Please wait a few seconds.']]);
            }

            return 'done';
        },
        fn (ValidationErrors $errors) => $errors->messageContains('global', 'Please wait'),
    );

    expect($host->attempts)->toBe(4);
});

it('keeps retrying while the user can be prompted for the field that failed', function () {
    $host = validatesHost();
    $host->form()->prompt('name', fn ($resolver) => $resolver->fromInput(fn ($value) => $value ?? 'taken'));

    $host->run(function () use ($host) {
        $host->attempts++;

        if ($host->attempts < 4) {
            throw validationException(['name' => ['The name has already been taken.']]);
        }

        return 'done';
    });

    expect($host->attempts)->toBe(4);
});
