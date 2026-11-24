<?php

namespace App\Commands;

use App\Client\Requests\SubmitFeedbackRequestData;
use Carbon\CarbonInterval;
use Saloon\Exceptions\Request\Statuses\TooManyRequestsException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class Feedback extends BaseCommand
{
    protected $signature = 'feedback
                            {message? : What got in the way (a wrong result, a confusing error, a docs gap, a missing endpoint), or the question}
                            {--type= : feedback (a problem) or question (something the docs and CLI could not answer)}
                            {--tool= : The CLI command, API endpoint, or MCP tool the feedback is about, e.g. deploy:monitor}
                            {--environment= : The ID of the environment the feedback is about}';

    protected $description = 'Tell the Laravel Cloud team what got in your way, or ask a question the docs could not answer';

    public function handle()
    {
        $this->ensureClient();

        intro('Feedback');

        $response = $this->loopUntilValid(fn () => $this->submit());

        $this->outputJsonIfWanted(['sent' => true, ...$response]);

        success('Thanks! Your feedback was sent to the Laravel Cloud team.');
    }

    protected function submit(): array
    {
        $this->form()->prompt(
            'type',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'What kind of feedback is this?',
                    options: [
                        'feedback' => 'Something got in the way',
                        'question' => 'A question the docs and CLI could not answer',
                    ],
                    default: $value ?? 'feedback',
                    info: fn ($value) => match ($value) {
                        'feedback' => 'Wrong result, confusing error, docs gap, missing endpoint',
                        default => '',
                    },
                ))
                ->nonInteractively(fn () => 'feedback'),
        );

        if (! in_array($this->form()->get('type'), ['feedback', 'question'], true)) {
            $this->failAndExit('--type must be feedback or question.');
        }

        $this->form()->prompt(
            'message',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => textarea(
                label: $this->form()->get('type') === 'question' ? 'What is your question?' : 'What got in the way?',
                default: $value ?? '',
                required: true,
                validate: fn (string $value) => mb_strlen($value) > 4000 ? 'Keep it under 4,000 characters.' : null,
            )),
        );

        $this->form()->prompt(
            'tool',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => text(
                    label: 'Which command is it about?',
                    placeholder: 'deploy:monitor',
                    default: $value ?? '',
                    hint: 'Optional. A CLI command, API endpoint, or MCP tool.',
                ))
                ->nonInteractively(fn () => ''),
        );

        try {
            return spin(
                fn () => $this->client->meta()->submitFeedback(new SubmitFeedbackRequestData(
                    type: $this->form()->get('type'),
                    message: $this->form()->get('message'),
                    tool: $this->form()->get('tool') ?: null,
                    environmentId: $this->option('environment') ?: null,
                )),
                'Sending feedback...',
            );
        } catch (TooManyRequestsException $e) {
            $seconds = (int) $e->getResponse()->header('Retry-After');

            $this->failAndExit(
                'Feedback is limited to 20 submissions a day for each organization and each user, and that limit has been reached.'
                    .($seconds > 0 ? ' Try again in '.CarbonInterval::seconds($seconds)->cascade()->forHumans(['parts' => 2]).'.' : ' Try again tomorrow.'),
            );
        }
    }
}
