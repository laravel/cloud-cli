<?php

namespace App\Prompts;

use Closure;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use RuntimeException;
use Throwable;

class DynamicSpinner extends Prompt
{
    use Colors;
    use InteractsWithStrings;

    /**
     * How long to wait between rendering each frame.
     */
    public int $interval = 100;

    public array $ellipsisFrames = ['', '.', '..', '...', '...'];

    public int $ellipsisCount = 0;

    /**
     * The number of times the spinner has been rendered.
     */
    public int $count = 0;

    /**
     * Whether the spinner can only be rendered once.
     */
    public bool $static = false;

    /**
     * The process ID after forking.
     */
    protected int $pid;

    /**
     * Socket pair for IPC between parent and child processes.
     *
     * @var resource[]|null
     */
    protected ?array $sockets = null;

    public string $lastMessage = '';

    public string $displayMessage = '';

    protected string $resetIdentifier = '';

    /**
     * Create a new Spinner instance.
     */
    public function __construct(public string $message = '')
    {
        $this->lastMessage = $message;
        $this->resetIdentifier = str()->random(10);
    }

    /**
     * Render the spinner and execute the callback.
     *
     * @template TReturn of mixed
     *
     * @param  Closure(callable(string): void): TReturn  $callback
     * @return TReturn
     */
    public function spin(Closure $callback): mixed
    {
        $this->capturePreviousNewLines();

        if (! function_exists('pcntl_fork')) {
            return $this->renderStatically($callback);
        }

        $originalAsync = pcntl_async_signals(true);

        pcntl_signal(SIGINT, fn () => exit());

        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($sockets === false) {
            return $this->renderStatically($callback);
        }

        $this->sockets = $sockets;

        try {
            $this->hideCursor();
            $this->render();

            $this->pid = pcntl_fork();

            if ($this->pid === 0) {
                fclose($this->sockets[0]);
                stream_set_blocking($this->sockets[1], false);

                while (true) { // @phpstan-ignore while.alwaysTrue
                    $this->checkForMessageUpdate();
                    $this->render();

                    $this->count++;

                    if ($this->count % 10 === 0) {
                        $this->ellipsisCount++;
                    }

                    if (! str($this->stripEscapeSequences($this->message))->endsWith(['.', '!'])) {
                        $this->displayMessage = $this->message.$this->dim($this->ellipsisFrames[$this->ellipsisCount % count($this->ellipsisFrames)]);
                    } else {
                        $this->displayMessage = $this->message;
                    }

                    usleep($this->interval * 1000);
                }
            } else {
                fclose($this->sockets[1]);

                $result = $callback($this->createMessageUpdater());

                $this->resetTerminal($originalAsync);

                return $result;
            }
        } catch (Throwable $e) {
            $this->resetTerminal($originalAsync);

            throw $e;
        }
    }

    /**
     * Create a callback that can be used to update the spinner message.
     */
    protected function createMessageUpdater(): Closure
    {
        return function (string $message, bool $resetEllipsis = false): void {
            if ($this->sockets !== null && is_resource($this->sockets[0])) {
                fwrite($this->sockets[0], $message.($resetEllipsis ? $this->resetIdentifier : '')."\x00");
            }
        };
    }

    /**
     * Check for message updates from the parent process.
     */
    protected function checkForMessageUpdate(): void
    {
        if ($this->sockets === null || ! is_resource($this->sockets[1])) {
            return;
        }

        $data = '';

        while (($chunk = fread($this->sockets[1], 1024)) !== false && $chunk !== '') {
            $data .= $chunk;
        }

        if ($data !== '') {
            $messages = explode("\x00", $data);
            $lastMessage = '';

            foreach (array_reverse($messages) as $msg) {
                if ($msg !== '') {
                    $lastMessage = $msg;

                    break;
                }
            }

            if ($lastMessage !== '') {
                if (str($lastMessage)->endsWith($this->resetIdentifier)) {
                    $this->ellipsisCount = 0;
                    $lastMessage = str($lastMessage)->beforeLast($this->resetIdentifier)->toString();
                }

                $this->message = $lastMessage;
            }
        }
    }

    /**
     * Reset the terminal.
     */
    protected function resetTerminal(bool $originalAsync): void
    {
        pcntl_async_signals($originalAsync);
        pcntl_signal(SIGINT, SIG_DFL);

        $this->killChildProcess();
        $this->closeSockets();
        $this->eraseRenderedLines();
    }

    /**
     * Kill the child process if it exists.
     */
    protected function killChildProcess(): void
    {
        if (! empty($this->pid) && $this->pid > 0) {
            posix_kill($this->pid, SIGHUP);
            pcntl_waitpid($this->pid, $status, WNOHANG);
        }
    }

    /**
     * Close socket connections.
     */
    protected function closeSockets(): void
    {
        if ($this->sockets !== null) {
            foreach ($this->sockets as $socket) {
                if (is_resource($socket)) {
                    fclose($socket);
                }
            }

            $this->sockets = null;
        }
    }

    /**
     * Render a static version of the spinner.
     *
     * @template TReturn of mixed
     *
     * @param  Closure(callable(string): void): TReturn  $callback
     * @return TReturn
     */
    protected function renderStatically(Closure $callback): mixed
    {
        $this->static = true;

        $noopUpdater = function (string $message): void {
            $this->message = $message;
        };

        try {
            $this->hideCursor();
            $this->render();

            $result = $callback($noopUpdater);
        } finally {
            $this->eraseRenderedLines();
        }

        return $result;
    }

    /**
     * Disable prompting for input.
     *
     * @throws RuntimeException
     */
    public function prompt(): never
    {
        throw new RuntimeException('Spinner cannot be prompted.');
    }

    /**
     * Get the current value of the prompt.
     */
    public function value(): bool
    {
        return true;
    }

    /**
     * Clear the lines rendered by the spinner.
     */
    protected function eraseRenderedLines(): void
    {
        $lines = explode(PHP_EOL, $this->prevFrame);
        $this->moveCursor(-999, -count($lines) + 1);
        $this->eraseDown();
    }

    /**
     * Clean up after the spinner.
     */
    public function __destruct()
    {
        $this->killChildProcess();
        $this->closeSockets();

        parent::__destruct();
    }
}
