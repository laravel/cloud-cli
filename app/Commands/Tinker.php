<?php

namespace App\Commands;

use App\Client\Requests\RunCommandRequestData;
use App\Exceptions\CommandExitException;
use App\Prompts\MonitorCommand;
use Carbon\CarbonInterval;
use Exception;
use Illuminate\Foundation\Concerns\ResolvesDumpSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\textarea;
use function Laravel\Prompts\warning;

class Tinker extends BaseCommand
{
    use ResolvesDumpSource;

    protected $signature = 'tinker
        {environment? : The environment ID or name}
        {--editor= : Open the code in the editor}';

    protected $description = 'Tinker in your Laravel Cloud environment';

    protected string $codeTmpFile;

    protected $tmpFileLastModifiedAt;

    protected const RECENT_SAVE_WINDOW_SECONDS = 2;

    protected ?string $editorUrl = null;

    public function handle()
    {
        $this->ensureClient();

        intro('Tinker');

        $this->resolveEditorUrl();
        dd($this->editorUrl);
        $environment = $this->resolvers()->environment()->include('application')->from($this->argument('environment'));

        if ($this->editorUrl) {
            info('Every time you save the file, the code will be executed.');
        }

        while (true) {
            $code = $this->getCodeForCommand();

            if ($code === null) {
                return self::SUCCESS;
            }

            if ($code === '') {
                continue;
            }

            if ($this->editorUrl) {
                codeBlock($code);
            }

            $command = spin(function () use ($code, $environment) {
                $response = Http::withToken('J7yZOdUpHNBTFoAZA8sm')
                    ->asJson()
                    ->post('https://joe.codes/api/tinker-snippets', [
                        'code' => $code,
                    ]);

                return $this->client->commands()->run(
                    new RunCommandRequestData(
                        environmentId: $environment->id,
                        command: 'curl -sSL https://joe.codes/api/tinker-snippets/'.$response->json('uuid').' | sh',
                        // command: 'php artisan tinker --execute=' . escapeshellarg($code),
                    ),
                );
            }, 'Running...');

            (new MonitorCommand(
                fn (string $id) => $this->client->commands()->get($id),
                $command,
                showCommand: false,
            ))->display();
        }
    }

    protected function resolveEditorUrl()
    {
        if ($this->input->getParameterOption('--editor', false) === false) {
            return null;
        }

        $editorKey = $this->option('editor') ?? getenv('VISUAL') ?: getenv('EDITOR');

        if (! $editorKey) {
            return null;
        }

        $editorKey = match ($editorKey) {
            'code' => 'vscode',
            'subl' => 'sublime',
            'nvim' => 'neovim',
            'vim', 'vi' => 'vim',
            'codium' => 'vscodium',
            default => $editorKey,
        };

        $this->editorUrl = $this->editorHrefs[$editorKey] ?? null;

        if (! $this->editorUrl) {
            error('Unknown editor. Valid values:');
            // TODO: Improve this, not sure what it should be
            info(implode(', ', array_keys($this->editorHrefs)));

            throw new CommandExitException(self::FAILURE);
        }
    }

    protected function getCodeForCommand()
    {
        if ($this->editorUrl) {
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

    protected function openInEditor(): ?string
    {
        $this->codeTmpFile ??= $this->initTmpFile();
        $this->tmpFileLastModifiedAt = filemtime($this->codeTmpFile);

        $result = spin(
            fn () => $this->waitForFileToBeSaved(),
            'Waiting for file to be saved...',
        );

        if (! is_array($result)) {
            return $result;
        }

        [$type, $message] = $result;

        match ($type) {
            'warning' => warning($message),
            'outro' => outro($message),
            default => throw new Exception('Invalid type: '.$type),
        };

        return null;
    }

    protected function waitForFileToBeSaved(): array|string
    {
        Sleep::for(CarbonInterval::milliseconds(500));

        while (true) {
            clearstatcache(true, $this->codeTmpFile);

            if (! file_exists($this->codeTmpFile)) {
                return ['warning', 'File no longer exists.'];
            }

            if (! $this->fileIsOpen($this->codeTmpFile) && ! $this->wasModifiedRecently($this->codeTmpFile)) {
                return ['outro', 'File closed, exiting tinker session.'];
            }

            if (filemtime($this->codeTmpFile) !== $this->tmpFileLastModifiedAt) {
                break;
            }

            Sleep::for(CarbonInterval::milliseconds(100));
        }

        $this->tmpFileLastModifiedAt = filemtime($this->codeTmpFile);

        return file_get_contents($this->codeTmpFile);
    }

    protected function fileIsOpen(string $path): bool
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $output = [];
            exec('lsof '.escapeshellarg($path).' 2>/dev/null', $output);

            return $output !== [];
        }

        if (PHP_OS_FAMILY === 'Linux') {
            $output = [];
            exec('lsof '.escapeshellarg($path).' 2>/dev/null', $output);

            if ($output !== []) {
                return true;
            }

            return $this->fileIsOpenViaProc($path);
        }

        return true;
    }

    protected function wasModifiedRecently(string $path): bool
    {
        $mtime = filemtime($path);

        if ($mtime === false) {
            return false;
        }

        return $mtime >= time() - static::RECENT_SAVE_WINDOW_SECONDS;
    }

    protected function fileIsOpenViaProc(string $path): bool
    {
        $resolved = realpath($path);

        if ($resolved === false) {
            return false;
        }

        $procFds = glob('/proc/*/fd');

        if ($procFds === false) {
            return true;
        }

        foreach ($procFds as $fdDir) {
            $fds = @scandir($fdDir);

            if ($fds === false) {
                continue;
            }

            foreach ($fds as $fd) {
                if ($fd === '.' || $fd === '..') {
                    continue;
                }

                $target = @readlink($fdDir.DIRECTORY_SEPARATOR.$fd);

                if ($target !== false && str_starts_with($target, '/')) {
                    $targetResolved = realpath($target);

                    if ($targetResolved !== false && $targetResolved === $resolved) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function initTmpFile(): string
    {
        $this->codeTmpFile = tempnam(sys_get_temp_dir(), 'laravel-cloud-tinker-');

        file_put_contents($this->codeTmpFile, '<?php '.PHP_EOL.PHP_EOL);

        exec(
            'open '.str_replace(
                ['{file}', '{line}'],
                [$this->codeTmpFile, 3],
                $this->editorUrl,
            ),
        );

        return $this->codeTmpFile;
    }
}
