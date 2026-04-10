<?php

namespace App\Commands;

use App\Client\Requests\CodeExecutionRequestData;
use App\Exceptions\CommandExitException;
use Carbon\CarbonInterval;
use Exception;
use Illuminate\Foundation\Concerns\ResolvesDumpSource;
use Illuminate\Support\Sleep;
use Saloon\Exceptions\Request\RequestException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\textarea;
use function Laravel\Prompts\warning;

class Tinker extends BaseCommand
{
    use ResolvesDumpSource;

    protected $signature = 'tinker
        {environment? : The environment ID or name}
        {--editor= : Open the code in the editor}
        {--timeout=60 : Maximum seconds to wait for output}';

    protected $description = 'Tinker in your Laravel Cloud environment';

    protected string $codeTmpFile;

    protected $tmpFileLastModifiedAt;

    protected const RECENT_SAVE_WINDOW_SECONDS = 2;

    protected ?string $editorUrl = null;

    public function handle()
    {
        $this->ensureClient();

        intro('Tinker');

        $environment = $this->resolvers()->environment()->include('application')->from($this->argument('environment'));

        $this->resolveEditorUrl();

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

            try {
                $codeExecution = spin(function () use ($code, $environment) {
                    return $this->client->codeExecutions()->create(
                        new CodeExecutionRequestData(
                            environmentId: $environment->id,
                            code: $code,
                        ),
                    );
                }, 'Running...');
            } catch (RequestException $e) {
                if ($e->getResponse()->status() === 422) {
                    $errors = $e->getResponse()->json('errors', []);

                    foreach ($errors as $field => $messages) {
                        error(ucwords($field).': '.implode(', ', $messages));
                    }

                    if (empty($errors)) {
                        error($e->getResponse()->json('message', 'Validation error'));
                    }
                } else {
                    error($e->getMessage());
                }

                continue;
            }

            $startedAt = time();
            $timeout = (int) $this->option('timeout');

            $result = spin(function () use ($codeExecution, $startedAt, $timeout) {
                while (true) {
                    if (time() - $startedAt >= $timeout) {
                        return null;
                    }

                    $codeExecution = $this->client->codeExecutions()->get($codeExecution->id);

                    if ($codeExecution->output !== null) {
                        return $codeExecution;
                    }

                    Sleep::for(CarbonInterval::second(2));
                }
            }, 'Waiting for output...');

            if ($result === null) {
                error('Code execution timed out.');

                continue;
            }

            if ($result->failureReason) {
                error($result->failureReason);
            } elseif ($result->exitCode !== 0 && $result->exitCode !== null) {
                error('Code execution failed (exit code: '.$result->exitCode.').');

                if ($result->output) {
                    codeBlock($result->output, 'result');
                }
            } elseif ($result->output) {
                codeBlock($result->output, 'result');
            }
        }
    }

    protected function resolveEditorUrl()
    {
        if ($this->input->getParameterOption('--editor', false) === false) {
            return;
        }

        $editorKey = $this->option('editor') ?: getenv('VISUAL') ?: getenv('EDITOR');

        if (! $editorKey) {
            warning('Tip: You can specify an editor by passing "--editor=code" or setting the VISUAL or EDITOR environment variables.');

            $editorKey = select(
                label: 'Editor',
                options: array_keys($this->editorHrefs),
            );
        }

        $editorKey = match ($editorKey) {
            'code' => 'vscode',
            'subl' => 'sublime',
            'nvim' => 'neovim',
            'vi' => 'vim',
            'codium' => 'vscodium',
            default => $editorKey,
        };

        $this->editorUrl = $this->editorHrefs[$editorKey] ?? null;

        if (! $this->editorUrl) {
            error('Unknown editor. Valid values:');
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
        if (! in_array(PHP_OS_FAMILY, ['Darwin', 'Linux'])) {
            return true;
        }

        $output = [];
        exec('lsof '.escapeshellarg($path).' 2>/dev/null', $output);

        if ($output !== []) {
            return true;
        }

        if (PHP_OS_FAMILY === 'Linux') {
            return $this->fileIsOpenViaProc($path);
        }

        return false;
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

        openUrl(
            str_replace(
                ['{file}', '{line}'],
                [$this->codeTmpFile, '3'],
                $this->editorUrl,
            ),
        );

        return $this->codeTmpFile;
    }
}
