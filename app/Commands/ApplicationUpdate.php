<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Illuminate\Http\Client\RequestException;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;

class ApplicationUpdate extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'application:update
                            {application : The application ID}
                            {--name= : Application name}
                            {--slack-channel= : Slack channel for notifications}
                            {--json : Output as JSON}';

    protected $description = 'Update an application';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating application');

        $data = [];

        if ($this->option('name')) {
            $data['name'] = $this->option('name');
        }

        if ($this->option('slack-channel')) {
            $data['slack_channel'] = $this->option('slack-channel');
        }

        if (empty($data)) {
            error('No fields to update. Provide at least one option.');

            return 1;
        }

        try {
            $application = spin(
                fn () => $this->client->updateApplication($this->argument('application'), $data),
                'Updating application...'
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'id' => $application->id,
                    'name' => $application->name,
                    'updated_at' => $application->updatedAt?->toIso8601String(),
                ], JSON_PRETTY_PRINT));

                return;
            }

            outro("Application updated: {$application->name}");
        } catch (RequestException $e) {
            if ($e->response?->status() === 422) {
                $errors = $e->response->json()['errors'] ?? [];
                foreach ($errors as $field => $messages) {
                    error(ucwords($field).': '.implode(', ', $messages));
                }
            } else {
                error('Failed to update application: '.$e->getMessage());
            }

            return 1;
        }
    }
}
