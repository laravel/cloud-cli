# Laravel Cloud CLI

A Laravel Zero CLI for deploying and managing applications on [Laravel Cloud](https://cloud.laravel.com). Authenticate via OAuth, create and manage applications, environments, databases, caches, object storage, domains, and more—all from the terminal.

## Requirements

- **PHP 8.2+**
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

This opens a browser for OAuth. API tokens are stored in `~/.config/cloud/config.json`. To manage tokens (e.g. for CI):

```sh
cloud auth:token
```

## Repository configuration

Link the current Git repo to a Laravel Cloud application and set defaults (application, environment) so you don’t have to pass them every time:

```sh
cloud repo:config
```

Run this from your project root after `cloud auth`.

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
