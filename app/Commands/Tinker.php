<?php

namespace App\Commands;

use App\Client\Requests\RunCommandRequestData;
use App\Prompts\MonitorCommand;
use Carbon\CarbonInterval;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\textarea;

class Tinker extends BaseCommand
{
    protected $signature = 'tinker
        {environment? : The environment ID or name}
        {--editor : Open the code in the editor}';

    protected $description = 'Tinker in your Laravel Cloud environment';

    protected string $codeTmpFile;

    protected $tmpFileLastModifiedAt;

    public function handle()
    {
        $this->ensureClient();

        intro('Tinker');

        $environment = $this->resolvers()->environment()->include('application')->from($this->argument('environment'));

        if ($this->option('editor')) {
            info('Every time you save the file, the code will be executed.');
        }

        while (true) {
            $code = $this->getCodeForCommand();
            $code = trim($code);

            if (str_starts_with($code, '<?php')) {
                $code = str_replace('<?php', '', $code);
            }

            $code = trim($code);

            if ($code === '') {
                continue;
            }

            $command = spin(fn () => $this->client->commands()->run(
                new RunCommandRequestData(
                    environmentId: $environment->id,
                    command: 'php artisan tinker --execute='.escapeshellarg($code),
                ),
            ), 'Running...');

            (new MonitorCommand(
                fn (string $id) => $this->client->commands()->get($id),
                $command,
            ))->display();
        }
    }

    protected function getCodeForCommand()
    {
        if ($this->option('editor')) {
            return $this->openInEditor();
        }

        return textarea(
            'Code',
            default: '<?php '.PHP_EOL.PHP_EOL,
            rows: 10,
            placeholder: 'Type your code here...',
            required: true,
        );
    }

    protected function openInEditor()
    {
        $this->codeTmpFile ??= $this->initTmpFile();
        $this->tmpFileLastModifiedAt = filemtime($this->codeTmpFile);

        while (filemtime($this->codeTmpFile) === $this->tmpFileLastModifiedAt) {
            clearstatcache(true, $this->codeTmpFile);
            Sleep::for(CarbonInterval::milliseconds(100));
        }

        $this->tmpFileLastModifiedAt = filemtime($this->codeTmpFile);

        return file_get_contents($this->codeTmpFile);
    }

    protected function initTmpFile(): string
    {
        $this->codeTmpFile = tempnam(sys_get_temp_dir(), 'laravel-cloud-tinker-');

        file_put_contents($this->codeTmpFile, '<?php '.PHP_EOL.PHP_EOL);

        exec('open vscode://file/'.$this->codeTmpFile.':3:1');

        return $this->codeTmpFile;
    }
}
