<?php

namespace App\Commands;

use App\Client\Connector;
use App\ConfigRepository;
use App\Contracts\NoAuthRequired;
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
            if ($e->getResponse()->status() === 422) {
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
            if ($e->getResponse()->status() === 422) {
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
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($socket === false) {
            error('Failed to create server socket.');

            return;
        }

        $this->serverSocket = $socket;

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

                if ($this->exchangeCode !== null) {
                    break;
                }
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
                $this->getSuccessHtml(),
            ];

            socket_write($clientSocket, implode("\r\n", $response));
        } else {
            $response = [
                'HTTP/1.1 400 Bad Request',
                'Content-Type: text/html',
                '',
                $this->getFailureHtml(),
            ];

            socket_write($clientSocket, implode("\r\n", $response));
        }

        socket_close($clientSocket);
    }

    protected function getFailureHtml()
    {
        return <<<'HTML'
        <!DOCTYPE html>
        <html lang="en" class="light-theme" style="color-scheme: light;">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Authentication Failed — Laravel Cloud</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,100..900&display=swap" rel="stylesheet">
            <style>
                * {
                    box-sizing: border-box;
                }
                body {
                    margin: 0;
                    min-height: 100vh;
                    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", system-ui, sans-serif;
                    background: #FFFFFF;
                    color: #1d1f21;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 1rem;
                }
                .card {
                    background: #ffffff;
                    border-radius: 6px;
                    box-shadow: rgba(0, 0, 0, 0.06) 0px 1px 2px 0px, rgba(0, 0, 0, 0.03) 0px 0px 1px 0px;
                    width: 100%;
                    max-width: 440px;
                    padding: 2rem;
                    text-align: center;
                }
                .icon {
                    width: 3rem;
                    height: 3rem;
                    margin: 0 auto 20px;
                    background: #eff0f1;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .icon svg {
                    width: 1.5rem;
                    height: 1.5rem;
                    color: #151718;
                }
                h1 {
                    margin: 0 0 8px;
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: #1d1f21;
                    letter-spacing: -0.02em;
                }
                p {
                    margin: 0;
                    font-size: 0.875rem;
                    line-height: 1.5;
                    color: #626465;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <h1>Authentication Failed</h1>
                <p>Invalid request or authentication was cancelled. Please try again from the terminal.</p>
            </div>
        </body>
        </html>
        HTML;
    }

    protected function getSuccessHtml()
    {
        return <<<'HTML'
        <!DOCTYPE html>
        <html lang="en" class="light-theme" style="color-scheme: light;">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Authentication Successful — Laravel Cloud</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,100..900&display=swap" rel="stylesheet">
            <style>
                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    min-height: 100vh;
                    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", system-ui, sans-serif;
                    background: #FFFFFF;
                    color: #1d1f21;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 1rem;
                }

                .card {
                    background: #ffffff;
                    border-radius: 6px;
                    box-shadow: rgba(0, 0, 0, 0.06) 0px 1px 2px 0px, rgba(0, 0, 0, 0.03) 0px 0px 1px 0px;
                    width: 100%;
                    max-width: 440px;
                    padding: 2rem;
                    text-align: center;
                }

                .icon {
                    width: 3rem;
                    height: 3rem;
                    margin: 0 auto 20px;
                    background: #eff0f1;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .icon svg {
                    width: 1.5rem;
                    height: 1.5rem;
                    color: #151718;
                }

                h1 {
                    margin: 0 0 8px;
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: #1d1f21;
                    letter-spacing: -0.02em;
                }

                p {
                    margin: 0;
                    font-size: 0.875rem;
                    line-height: 1.5;
                    color: #626465;
                }
            </style>
        </head>

        <body>
            <div class="card">
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <h1>Authentication Successful</h1>
                <p>You can close this window and return to the terminal.</p>
            </div>
        </body>

        </html>
        HTML;
    }
}
