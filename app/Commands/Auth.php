<?php

namespace App\Commands;

use App\Client\Connector;
use App\ConfigRepository;
use App\Contracts\NoAuthRequired;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Saloon\Exceptions\Request\RequestException as SaloonRequestException;
use Socket;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;

class Auth extends BaseCommand implements NoAuthRequired
{
    protected $signature = 'auth';

    protected $description = 'Authenticate with Laravel Cloud using browser-based OAuth';

    protected ConfigRepository $config;

    protected ?int $port = null;

    protected ?Socket $serverSocket = null;

    protected ?string $exchangeCode = null;

    public function handle()
    {
        if (! extension_loaded('sockets')) {
            error('The sockets extension is required for browser-based authentication.');

            return 1;
        }

        $this->config = app(ConfigRepository::class);

        intro('Laravel Cloud Authentication');

        $this->port = $this->findAvailablePort();

        if ($this->port === null) {
            error('Could not find an available port.');

            return 1;
        }

        info("Starting local server on port {$this->port}...");

        try {
            $redirectUrl = spin(
                fn () => (new Connector(''))->cliAuth()->createAuthSession($this->port),
                'Creating auth session...',
            );
        } catch (SaloonRequestException $e) {
            if ($e->getResponse()?->status() === 422) {
                $errors = $e->getResponse()->json('errors', []);

                foreach ($errors as $field => $messages) {
                    error(ucwords($field).': '.implode(', ', $messages));
                }
            } else {
                error('Failed to create auth session: '.$e->getMessage());
            }

            return 1;
        }

        info("Opening browser: {$redirectUrl}");

        Process::run("open {$redirectUrl}");

        info('Waiting for authentication...');

        $this->startServer();

        if ($this->exchangeCode === null) {
            error('Authentication cancelled or timed out.');

            return 1;
        }

        try {
            $tokens = spin(
                fn () => (new Connector(''))->cliAuth()->exchangeCode($this->exchangeCode),
                'Exchanging code for tokens...',
            );
        } catch (SaloonRequestException $e) {
            if ($e->getResponse()?->status() === 422) {
                $message = $e->getResponse()->json('message', 'Invalid exchange code.');

                error($message);
            } else {
                error('Failed to exchange code: '.$e->getMessage());
            }

            return 1;
        }

        foreach ($tokens as $tokenData) {
            $this->config->addApiToken($tokenData['token']);

            info("✓ Authenticated with {$tokenData['organization_name']}");
        }

        outro('Authentication successful! Tokens saved to '.$this->config->path());

        return 0;
    }

    protected function findAvailablePort(int $startPort = 49513, int $maxAttempts = 100): ?int
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $port = $startPort + $i;

            $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

            if ($socket === false) {
                continue;
            }

            socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

            $bound = @socket_bind($socket, '127.0.0.1', $port);

            if ($bound) {
                socket_close($socket);

                return $port;
            }

            socket_close($socket);
        }

        return null;
    }

    protected function startServer(): void
    {
        $this->serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($this->serverSocket === false) {
            error('Failed to create server socket.');

            return;
        }

        socket_set_option($this->serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (! socket_bind($this->serverSocket, '127.0.0.1', $this->port)) {
            error('Failed to bind server socket.');

            return;
        }

        socket_listen($this->serverSocket, 5);

        socket_set_nonblock($this->serverSocket);

        $startTime = time();
        $timeout = 300;

        while (true) {
            $clientSocket = @socket_accept($this->serverSocket);

            if ($clientSocket !== false) {
                $this->handleRequest($clientSocket);

                break;
            }

            if (time() - $startTime > $timeout) {
                break;
            }

            usleep(100000);
        }

        socket_close($this->serverSocket);
    }

    protected function handleRequest(Socket $clientSocket): void
    {
        $request = '';

        while ($data = socket_read($clientSocket, 1024)) {
            $request .= $data;

            if (str_contains($request, "\r\n\r\n")) {
                break;
            }
        }

        if (preg_match('/GET \/[^?\s]*[?&]exchange_code=([^&\s]+)/', $request, $matches)) {
            $this->exchangeCode = urldecode($matches[1]);

            $response = [
                'HTTP/1.1 200 OK',
                'Content-Type: text/html',
                '',
                File::get(resource_path('html/auth-success.html')),
            ];

            socket_write($clientSocket, implode("\r\n", $response));
        } else {
            $response = [
                'HTTP/1.1 400 Bad Request',
                'Content-Type: text/html',
                '',
                File::get(resource_path('html/auth-failure.html')),
            ];

            socket_write($clientSocket, implode("\r\n", $response));
        }

        socket_close($clientSocket);
    }
}
