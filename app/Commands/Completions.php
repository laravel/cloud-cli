<?php

namespace App\Commands;

use App\Contracts\NoAuthRequired;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\DumpCompletionCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;

class Completions extends BaseCommand implements NoAuthRequired
{
    protected $signature = 'completions
                            {shell? : The shell type (bash, zsh, fish)}
                            {--print : Print the completion script to stdout}';

    protected $description = 'Generate and install shell completion scripts';

    protected string $binaryName = 'cloud';

    public function handle(): int
    {
        $detectedShell = $this->detectShell();
        $shell = $this->argument('shell');

        intro('Shell Completion Setup');

        if ($this->option('print')) {
            $shell = $shell ?: $detectedShell;
            $this->printCompletionScript($shell);

            return self::SUCCESS;
        }

        if (! $shell) {
            $shell = select(
                label: 'Which shell do you use?',
                options: [
                    'zsh' => 'Zsh',
                    'bash' => 'Bash',
                    'fish' => 'Fish',
                ],
                default: $detectedShell,
            );
        }

        return $this->installCompletions($shell);
    }

    protected function detectShell(): string
    {
        $shell = getenv('SHELL') ?: '/bin/zsh';
        $shellName = basename($shell);

        return match ($shellName) {
            'zsh' => 'zsh',
            'bash' => 'bash',
            'fish' => 'fish',
            default => 'zsh',
        };
    }

    protected function installCompletions(string $shell): int
    {
        $this->ensureInteractive('Shell completions must be installed interactively. Use --print to output the script.');

        intro('Shell Completion Setup');

        $completionsDir = $this->getCompletionsDirectory($shell);
        $completionFile = $completionsDir.'/'.$this->getCompletionFilename($shell);

        if (! is_dir($completionsDir)) {
            if (! confirm("Create completions directory at {$completionsDir}?")) {
                error('Cancelled');

                return self::SUCCESS;
            }

            mkdir($completionsDir, 0755, true);
        }

        $fileExists = file_exists($completionFile);
        $confirmMessage = $fileExists
            ? "Overwrite existing completion script at {$completionFile}?"
            : "Write completion script to {$completionFile}?";

        if (! confirm($confirmMessage)) {
            error('Cancelled');

            return self::SUCCESS;
        }

        $script = $this->getCompletionScript($shell);
        file_put_contents($completionFile, $script);

        success("Completion script written to {$completionFile}");

        $this->showPostInstallInstructions($shell, $completionsDir);

        return self::SUCCESS;
    }

    protected function getCompletionFilename(string $shell): string
    {
        return match ($shell) {
            'zsh' => '_'.$this->binaryName,
            'bash' => $this->binaryName,
            'fish' => $this->binaryName.'.fish',
            default => $this->binaryName,
        };
    }

    protected function getCompletionsDirectory(string $shell): string
    {
        $home = $_SERVER['HOME'];

        return match ($shell) {
            'zsh' => $this->getZshCompletionsDirectory(),
            'bash' => $this->getBashCompletionsDirectory(),
            'fish' => $home.'/.config/fish/completions',
            default => $home.'/.local/share/completions',
        };
    }

    protected function getZshCompletionsDirectory(): string
    {
        $homebrewDirs = [
            '/opt/homebrew/share/zsh/site-functions',
            '/usr/local/share/zsh/site-functions',
        ];

        foreach ($homebrewDirs as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }

        return $_SERVER['HOME'].'/.zsh/completions';
    }

    protected function getBashCompletionsDirectory(): string
    {
        $dirs = [
            '/opt/homebrew/etc/bash_completion.d',
            '/usr/local/etc/bash_completion.d',
            $_SERVER['HOME'].'/.local/share/bash-completion/completions',
        ];

        foreach ($dirs as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }

        return $_SERVER['HOME'].'/.bash_completion.d';
    }

    protected function showPostInstallInstructions(string $shell, string $completionsDir): void
    {
        $this->newLine();

        match ($shell) {
            'zsh' => $this->showZshInstructions($completionsDir),
            'bash' => $this->showBashInstructions($completionsDir),
            'fish' => $this->showFishInstructions(),
            default => null,
        };
    }

    protected function showZshInstructions(string $completionsDir): void
    {
        $toAdd = collect([
            "fpath=({$completionsDir} \$fpath)",
            'autoload -Uz compinit && compinit',
        ]);

        $zshrcPath = $_SERVER['HOME'].'/.zshrc';

        if (! $this->isDirectoryInPath($completionsDir, 'FPATH')) {
            note('<comment>'.$toAdd->implode(PHP_EOL).'</comment>');

            if (File::exists($zshrcPath) && confirm('Add the above to your ~/.zshrc?')) {
                $content = File::get($zshrcPath);

                foreach ($toAdd as $line) {
                    if (! str_contains($content, $line)) {
                        File::append(path: $zshrcPath, data: $line.PHP_EOL);
                    }
                }
            } else {
                info('Add the following to your ~/.zshrc:');
                $this->newLine();
                note('<comment>'.$toAdd->implode(PHP_EOL).'</comment>');
                $this->newLine();
            }

            info('Then reload your shell: <comment>exec zsh</comment>');
        } else {
            info('Reload your shell to enable completions: <comment>exec zsh</comment>');
        }
    }

    protected function showBashInstructions(string $completionsDir): void
    {
        $homeDir = $_SERVER['HOME'];
        $isUserDir = str_starts_with($completionsDir, $homeDir);

        if ($isUserDir && str_contains($completionsDir, '.bash_completion.d')) {
            info('Add the following to your ~/.bashrc:');
            $this->newLine();
            $this->line("  <comment>for f in {$completionsDir}/*; do source \"\$f\"; done</comment>");
            $this->newLine();
        }

        info('Reload your shell to enable completions: <comment>exec bash</comment>');
    }

    protected function showFishInstructions(): void
    {
        info('Completions will be available in new Fish sessions.');
        $this->newLine();
        info('Or reload now: <comment>source ~/.config/fish/config.fish</comment>');
    }

    protected function isDirectoryInPath(string $directory, string $pathVar): bool
    {
        $path = getenv($pathVar) ?: '';

        return str_contains($path, $directory);
    }

    protected function getCompletionScript(string $shell): string
    {
        $command = new DumpCompletionCommand;
        $command->setApplication($this->getApplication());

        $input = new ArrayInput([
            'shell' => $shell,
            '--' => [],
        ]);

        $output = new BufferedOutput;

        $command->run($input, $output);

        return $output->fetch();
    }

    protected function printCompletionScript(string $shell): void
    {
        $script = $this->getCompletionScript($shell);

        $this->output->write($script);
    }
}
