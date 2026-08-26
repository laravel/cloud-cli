# Laravel Cloud CLI

A Laravel Zero CLI for deploying and managing applications on [Laravel Cloud](https://cloud.laravel.com). Authenticate via OAuth, create and manage applications, environments, databases, caches, object storage, domains, and more—all from the terminal.

## Requirements

- **PHP 8.3+**
- **Composer**
- **GitHub CLI (`gh`)** — installed and authenticated (used for repo linking and GitHub API)
- **Git** — for repository detection and `repo:config`

## Installation

Clone the repository and install dependencies:

```sh
gh repo clone laravel/cloud-cli
cd cloud-cli
composer install
```

## Setup Alias

To use the `cloud` command from anywhere, add an alias to your shell configuration:

**For Zsh (macOS default):**

```sh
echo 'alias cloud="php '$(pwd)'/cloud"' >> ~/.zshrc
source ~/.zshrc
```

**For Bash:**

```sh
echo 'alias cloud="php '$(pwd)'/cloud"' >> ~/.bashrc
source ~/.bashrc
```

Or manually add the alias to your `~/.zshrc` or `~/.bashrc` file:

```sh
alias cloud="php /path/to/cloud-cli/cloud"
```

## Authentication

Before using most commands, authenticate with Laravel Cloud:

```sh
cloud auth
```

This opens a browser for OAuth. API tokens are stored in `~/.config/cloud/config.json`. To manage those tokens interactively:

```sh
cloud auth:token
```

### CI and other non-interactive use

Browser OAuth needs a browser, so set the token in the environment instead. `LARAVEL_CLOUD_TOKEN` takes precedence over anything saved in `~/.config/cloud/config.json`, and nothing is written to disk:

```sh
export LARAVEL_CLOUD_TOKEN=your-api-token
cloud application:list -n
```

Create a token at https://cloud.laravel.com/docs/api/authentication#create-an-api-token.

An empty value counts as unset, so you can bypass it for a single command without unsetting it:

```sh
LARAVEL_CLOUD_TOKEN= cloud application:list -n
```

To save a token to the config file instead — on a machine you come back to, or when you need tokens for several organizations — pass it or pipe it:

```sh
cloud auth:token --add --token=your-api-token -n
echo "$YOUR_API_TOKEN" | cloud auth:token --add -n
```

Prefer the pipe where the process list is visible to others. `--remove` takes `--token=` the same way, and `--list` names the source of each token:

```sh
cloud auth:token --list -n
```

Listed tokens are masked down to their last four characters. Pass `--show-sensitive` to print them in full.

The token carries its own organization, so `--organization` becomes a check rather than a choice: when `LARAVEL_CLOUD_TOKEN` is set and you name an organization the token doesn't belong to, the command fails instead of using the wrong one.

## Repository configuration

Link the current Git repo to a Laravel Cloud application and set defaults (application, environment) so you don’t have to pass them every time:

```sh
cloud repo:config
```

Run this from your project root after `cloud auth`. Pass the application to skip the prompt, which is required when running non-interactively with more than one application:

```sh
cloud repo:config my-app -n
```

If you have tokens for more than one organization, name the one you want with `--organization` (its ID, name, or slug):

```sh
cloud repo:config my-app --organization=acme -n
```

## Quick start

1. **Ship** — Guided flow to create an application and deploy it:

    ```sh
    cloud ship
    ```

2. **Deploy** — Deploy an existing application (uses repo config or prompts):

    ```sh
    cloud deploy
    ```

3. **Dashboard** — Open the app in the Laravel Cloud dashboard:

    ```sh
    cloud dashboard
    ```

4. **Shell completions** — Enable tab completion:
    ```sh
    cloud completions
    ```

## Commands reference

Many commands accept an optional resource ID/name and support `--json` for machine-readable output. When run interactively without arguments, the CLI will prompt for application, environment, or other context as needed.

### Auth & config

| Command             | Description                                     |
| ------------------- | ----------------------------------------------- |
| `cloud auth`        | Authenticate with Laravel Cloud (browser OAuth) |
| `cloud auth:token`  | Manage API tokens                               |
| `cloud repo:config` | Configure defaults for the current repository   |

### Applications

| Command                    | Description             |
| -------------------------- | ----------------------- |
| `cloud application:list`   | List applications       |
| `cloud application:get`    | Get application details |
| `cloud application:create` | Create an application   |
| `cloud application:update` | Update an application   |
| `cloud application:delete` | Delete an application   |

### Environments

| Command                       | Description                                            |
| ----------------------------- | ------------------------------------------------------ |
| `cloud environment:list`      | List environments                                      |
| `cloud environment:get`       | Get environment details                                |
| `cloud environment:create`    | Create an environment                                  |
| `cloud environment:update`    | Update an environment                                  |
| `cloud environment:delete`    | Delete an environment                                  |
| `cloud environment:variables` | Manage environment variables (append, set, or replace) |
| `cloud environment:logs`      | View environment logs                                  |

### Secrets

Secrets are organization-wide encrypted values that can be attached to environments. Values are encrypted locally with the organization's public key before they are sent, so plaintext never leaves your machine.

| Command                           | Description                             |
| --------------------------------- | --------------------------------------- |
| `cloud secret:list`               | List secrets                            |
| `cloud secret:create`             | Create a secret                         |
| `cloud secret:update`             | Update a secret                         |
| `cloud secret:delete`             | Delete a secret                         |
| `cloud environment-secret:list`   | List secrets attached to an environment |
| `cloud environment-secret:attach` | Attach secrets to an environment        |

### Deploy & ship

| Command                 | Description                                    |
| ----------------------- | ---------------------------------------------- |
| `cloud ship`            | Ship the application to Laravel Cloud (guided) |
| `cloud deploy`          | Deploy to Laravel Cloud                        |
| `cloud deploy:monitor`  | Monitor deployments                            |
| `cloud deployment:list` | List deployments                               |
| `cloud deployment:get`  | Get deployment details                         |

### Instances

| Command                 | Description                   |
| ----------------------- | ----------------------------- |
| `cloud instance:list`   | List instances                |
| `cloud instance:get`    | Get instance details          |
| `cloud instance:create` | Create an instance            |
| `cloud instance:update` | Update an instance            |
| `cloud instance:delete` | Delete an instance            |
| `cloud instance:sizes`  | List available instance sizes |

### Databases

| Command                          | Description                            |
| -------------------------------- | -------------------------------------- |
| `cloud database-cluster:list`    | List database clusters                 |
| `cloud database-cluster:get`     | Get cluster details                    |
| `cloud database-cluster:create`  | Create a database cluster              |
| `cloud database-cluster:update`  | Update a database cluster              |
| `cloud database-cluster:delete`  | Delete a database cluster              |
| `cloud database:list`            | List databases (schemas) in a cluster  |
| `cloud database:get`             | Get database details                   |
| `cloud database:create`          | Create a database                      |
| `cloud database:delete`          | Delete a database                      |
| `cloud database:open`            | Open database locally                  |
| `cloud database-snapshot:list`   | List snapshots                         |
| `cloud database-snapshot:get`    | Get snapshot details                   |
| `cloud database-snapshot:create` | Create a snapshot                      |
| `cloud database-snapshot:delete` | Delete a snapshot                      |
| `cloud database-restore:create`  | Create a restore from snapshot or PITR |

### Cache

| Command              | Description                |
| -------------------- | -------------------------- |
| `cloud cache:list`   | List caches                |
| `cloud cache:get`    | Get cache details          |
| `cloud cache:create` | Create a cache             |
| `cloud cache:update` | Update a cache             |
| `cloud cache:delete` | Delete a cache             |
| `cloud cache:types`  | List available cache types |

### Object storage (buckets)

| Command                   | Description            |
| ------------------------- | ---------------------- |
| `cloud bucket:list`       | List buckets           |
| `cloud bucket:get`        | Get bucket details     |
| `cloud bucket:create`     | Create a bucket        |
| `cloud bucket:update`     | Update a bucket        |
| `cloud bucket:delete`     | Delete a bucket        |
| `cloud bucket-key:list`   | List bucket keys       |
| `cloud bucket-key:get`    | Get bucket key details |
| `cloud bucket-key:create` | Create a bucket key    |
| `cloud bucket-key:update` | Update a bucket key    |
| `cloud bucket-key:delete` | Delete a bucket key    |

### Domains

| Command               | Description        |
| --------------------- | ------------------ |
| `cloud domain:list`   | List domains       |
| `cloud domain:get`    | Get domain details |
| `cloud domain:create` | Create a domain    |
| `cloud domain:update` | Update a domain    |
| `cloud domain:delete` | Delete a domain    |
| `cloud domain:verify` | Verify domain DNS  |

### WebSockets

| Command                              | Description                    |
| ------------------------------------ | ------------------------------ |
| `cloud websocket-cluster:list`       | List WebSocket clusters        |
| `cloud websocket-cluster:get`        | Get cluster details            |
| `cloud websocket-cluster:create`     | Create a WebSocket cluster     |
| `cloud websocket-cluster:update`     | Update a WebSocket cluster     |
| `cloud websocket-cluster:delete`     | Delete a WebSocket cluster     |
| `cloud websocket-application:list`   | List WebSocket applications    |
| `cloud websocket-application:get`    | Get application details        |
| `cloud websocket-application:create` | Create a WebSocket application |
| `cloud websocket-application:update` | Update a WebSocket application |
| `cloud websocket-application:delete` | Delete a WebSocket application |

### Background processes

| Command                           | Description                 |
| --------------------------------- | --------------------------- |
| `cloud background-process:list`   | List background processes   |
| `cloud background-process:get`    | Get process details         |
| `cloud background-process:create` | Create a background process |
| `cloud background-process:update` | Update a background process |
| `cloud background-process:delete` | Delete a background process |

### Commands (scheduled/one-off)

| Command              | Description                      |
| -------------------- | -------------------------------- |
| `cloud command:list` | List commands for an environment |
| `cloud command:get`  | Get command details              |
| `cloud command:run`  | Run a command on an environment  |

### Usage

| Command                          | Description                                                  |
| -------------------------------- | ------------------------------------------------------------ |
| `cloud usage`                    | View billing summary for the current period                  |
| `cloud usage --detailed`         | Full breakdown with per-app, per-resource, and add-on tables |
| `cloud usage --period=previous`  | View a previous billing period (current, previous, 1, 2, 3)  |
| `cloud usage --environment=<id>` | Filter usage by environment                                  |

### Other

| Command                        | Description                              |
| ------------------------------ | ---------------------------------------- |
| `cloud dashboard`              | Open app in Cloud dashboard              |
| `cloud browser`                | Open the application in the browser      |
| `cloud ip:addresses`           | Get Laravel Cloud IP addresses by region |
| `cloud dedicated-cluster:list` | List dedicated clusters                  |
| `cloud completions`            | Generate and install shell completions   |

## Configuration

- **User config:** `~/.config/cloud/config.json` (auth tokens and preferences).
- **Repo defaults:** After `cloud repo:config`, the current Git repo stores which application and environment to use so you can run `cloud deploy` and similar without selecting every time.

## Development

- **Code style:** Laravel/PSR-12. Format with [Laravel Pint](https://laravel.com/docs/pint):
    ```sh
    ./vendor/bin/pint --dirty
    ```
- **Tests:** [Pest](https://pestphp.com/):
    ```sh
    ./vendor/bin/pest
    ```
- **Static analysis:** [PHPStan](https://phpstan.org/):
    ```sh
    ./vendor/bin/phpstan analyse
    ```

## Links

- [Laravel Cloud](https://cloud.laravel.com)
- [Laravel Cloud API docs](https://cloud.laravel.com/docs/api/introduction)
- [Laravel Zero](https://laravel-zero.com)
