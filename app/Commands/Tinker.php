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

    protected const RECENT_SAVE_WINDOW_SECONDS = 2;

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

            if ($code === null) {
                return 0;
            }

            $code = trim($code);

            if (str_starts_with($code, '<?php')) {
                $code = str_replace('<?php', '', $code);
            }

            $code = trim($code);

            if ($code === '') {
                continue;
            }

            if ($this->option('editor')) {
                answered('Code', $code);
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
                showCommand: false,
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

    protected function openInEditor(): ?string
    {
        $this->codeTmpFile ??= $this->initTmpFile();
        $this->tmpFileLastModifiedAt = filemtime($this->codeTmpFile);

        Sleep::for(CarbonInterval::milliseconds(500));

        while (true) {
            clearstatcache(true, $this->codeTmpFile);

            if (! file_exists($this->codeTmpFile)) {
                return null;
            }

            if (! $this->fileIsOpen($this->codeTmpFile) && ! $this->wasModifiedRecently($this->codeTmpFile)) {
                return null;
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

        exec('open vscode://file/'.$this->codeTmpFile.':3:1');

        return $this->codeTmpFile;
    }
}
