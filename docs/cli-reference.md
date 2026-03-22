# Laravel Cloud CLI Reference

The Laravel Cloud CLI is a command-line tool for managing Laravel Cloud applications, environments, and resources directly from your terminal.

## Requirements

- PHP 8.2+
- Composer
- Git
- GitHub CLI (`gh`) — optional, recommended for repository linking

## Installation

```sh
composer global require laravel/cloud-cli
```

Or clone and install manually:

```sh
gh repo clone laravel/cloud-cli
cd cloud-cli
composer install
```

To use the `cloud` command from anywhere, add an alias:

```sh
# Zsh (macOS default)
echo 'alias cloud="php /path/to/cloud-cli/cloud"' >> ~/.zshrc
source ~/.zshrc

# Bash
echo 'alias cloud="php /path/to/cloud-cli/cloud"' >> ~/.bashrc
source ~/.bashrc
```

## Authentication

### Browser OAuth (recommended)

```sh
cloud auth
```

Opens a browser for OAuth authentication. Tokens are stored in `~/.config/cloud/config.json`.

### Token management

```sh
# List tokens
cloud auth:token --list

# Add a token manually (useful for CI)
cloud auth:token --add

# Remove a token
cloud auth:token --remove
```

You can also pass a token directly to any command:

```sh
cloud application:list --token=your-api-token
```

Or set it via environment variable:

```sh
export CLOUD_API_TOKEN=your-api-token
```

## Getting started

### Ship (guided first deploy)

The fastest way to deploy a new application. Walks you through creating an application, configuring your environment, and deploying:

```sh
cloud ship
```

Preview what would happen without creating anything:

```sh
cloud ship --dry-run
```

### Deploy an existing application

```sh
cloud deploy
```

### Repository configuration

Link the current Git repo to a Laravel Cloud application so you don't have to specify it every time:

```sh
cloud repo:config
```

This creates `.cloud/config.json` in your project root.

## Global flags

These flags work across all commands:

| Flag | Description |
|------|-------------|
| `--token=<token>` | Pass an API token directly (overrides stored tokens and env var) |
| `--hide-secrets` | Redact passwords, API keys, and tokens in all output |
| `--application=<name-or-id>` | Specify application without interactive prompts |
| `--environment=<name-or-id>` | Specify environment without interactive prompts |
| `--json` | Machine-readable JSON output (supported by most commands) |
| `--force` | Skip confirmation prompts on destructive commands |

### Scripting and CI/CD

Combine global flags for fully non-interactive usage:

```sh
# Deploy a specific app/environment in CI
cloud deploy --application=my-app --environment=production --token=$CLOUD_TOKEN

# Get environment variables as JSON
cloud environment:variables my-env --json --token=$CLOUD_TOKEN

# Delete an app without confirmation
cloud application:delete my-app --force --token=$CLOUD_TOKEN
```

## Commands

### Applications

```sh
# List all applications
cloud application:list

# Get application details
cloud application:get [name-or-id]

# Create an application
cloud application:create

# Update an application
cloud application:update [name-or-id] --name=new-name --repository=org/repo

# Delete an application
cloud application:delete [name-or-id] --force
```

### Environments

```sh
# List environments for an application
cloud environment:list

# Get environment details
cloud environment:get [name-or-id]

# Create an environment
cloud environment:create

# Update an environment
cloud environment:update [name-or-id]

# Delete an environment
cloud environment:delete [name-or-id] --force

# Start a stopped environment
cloud environment:start [name-or-id]

# Stop a running environment
cloud environment:stop [name-or-id]

# View environment logs
cloud environment:logs [name-or-id]

# View environment metrics (CPU, memory, network)
cloud environment:metrics [name-or-id]
```

### Environment variables

```sh
# List variables
cloud environment:variables [environment]

# Set a variable
cloud environment:variables [environment] --action=set --key=APP_DEBUG --value=false

# Append variables (adds without removing existing)
cloud environment:variables [environment] --action=append --key=NEW_VAR --value=hello

# Replace all variables
cloud environment:variables [environment] --action=replace --key=APP_KEY --value=base64:...

# Delete a variable
cloud environment:variables [environment] --action=delete --key=OLD_VAR
```

### Env file sync

Sync environment variables between your local `.env` file and Laravel Cloud:

```sh
# Download environment variables as a .env file
cloud env:pull

# Upload local .env file (merges with existing variables)
cloud env:push

# Upload and replace all variables
cloud env:push --replace
```

### Deployments

```sh
# Deploy the current application
cloud deploy

# Preview deployment without executing
cloud deploy --dry-run

# Monitor an active deployment
cloud deploy:monitor

# List deployment history
cloud deployment:list

# Get deployment details
cloud deployment:get [deployment-id]

# View build and deploy logs for a deployment
cloud deployment:logs [deployment-id]
```

When a deployment fails, the CLI automatically shows which phase failed (Build or Deploy) along with the relevant error output.

### Instances

```sh
# List instances
cloud instance:list

# Get instance details
cloud instance:get [instance-id]

# Create an instance
cloud instance:create

# Update an instance
cloud instance:update [instance-id]

# Delete an instance
cloud instance:delete [instance-id] --force

# List available instance sizes
cloud instance:sizes
```

### Database clusters

```sh
# List database clusters
cloud database-cluster:list

# Get cluster details
cloud database-cluster:get [name-or-id]

# Create a database cluster
cloud database-cluster:create

# Update a database cluster
cloud database-cluster:update [name-or-id]

# Delete a database cluster
cloud database-cluster:delete [name-or-id] --force

# View cluster metrics
cloud database-cluster:metrics [name-or-id]
```

### Databases

```sh
# List databases in a cluster
cloud database:list

# Get database details
cloud database:get [name-or-id]

# Create a database
cloud database:create

# Delete a database
cloud database:delete [name-or-id] --force

# Open database in a local GUI client (e.g. TablePlus)
cloud database:open [cluster] [database]

# Show the connection URL (includes credentials)
cloud database:open [cluster] [database] --show-url
```

### Database snapshots and restores

```sh
# List snapshots
cloud database-snapshot:list

# Get snapshot details
cloud database-snapshot:get [snapshot-id]

# Create a snapshot
cloud database-snapshot:create

# Delete a snapshot
cloud database-snapshot:delete [snapshot-id]

# Restore from a snapshot
cloud database-restore:create
```

> **Note:** Snapshot support depends on your database cluster type. Neon serverless Postgres does not support manual snapshots.

### Caches

```sh
# List caches
cloud cache:list

# Get cache details
cloud cache:get [name-or-id]

# Create a cache
cloud cache:create

# Update a cache
cloud cache:update [name-or-id]

# Delete a cache
cloud cache:delete [name-or-id] --force

# View cache metrics
cloud cache:metrics [name-or-id]

# List available cache types and sizes
cloud cache:types
```

### Object storage (buckets)

```sh
# List buckets
cloud bucket:list

# Get bucket details
cloud bucket:get [name-or-id]

# Create a bucket
cloud bucket:create

# Update a bucket
cloud bucket:update [name-or-id]

# Delete a bucket
cloud bucket:delete [name-or-id] --force
```

#### Bucket keys

```sh
# List keys for a bucket
cloud bucket-key:list

# Get key details
cloud bucket-key:get [name-or-id]

# Create a key
cloud bucket-key:create

# Update a key
cloud bucket-key:update [name-or-id]

# Delete a key
cloud bucket-key:delete [name-or-id] --force
```

### Domains

```sh
# List domains for an environment
cloud domain:list

# Get domain details
cloud domain:get [name-or-id]

# Create a domain
cloud domain:create

# Update a domain
cloud domain:update [name-or-id]

# Delete a domain
cloud domain:delete [name-or-id] --force

# Verify domain DNS/SSL
cloud domain:verify [name-or-id]
```

### WebSocket clusters

```sh
# List WebSocket clusters
cloud websocket-cluster:list

# Get cluster details
cloud websocket-cluster:get [name-or-id]

# Create a WebSocket cluster
cloud websocket-cluster:create

# Update a WebSocket cluster
cloud websocket-cluster:update [name-or-id]

# Delete a WebSocket cluster
cloud websocket-cluster:delete [name-or-id] --force

# View cluster metrics
cloud websocket-cluster:metrics [name-or-id]
```

### WebSocket applications

```sh
# List WebSocket applications
cloud websocket-application:list

# Get application details
cloud websocket-application:get [name-or-id]

# Create a WebSocket application
cloud websocket-application:create

# Update a WebSocket application
cloud websocket-application:update [name-or-id]

# Delete a WebSocket application
cloud websocket-application:delete [name-or-id] --force

# View application metrics
cloud websocket-application:metrics [name-or-id]
```

### Background processes

```sh
# List background processes
cloud background-process:list

# Get process details
cloud background-process:get [process-id]

# Create a background process
cloud background-process:create

# Update a background process
cloud background-process:update [process-id]

# Delete a background process
cloud background-process:delete [process-id] --force
```

### Remote commands

Run commands on your Cloud environment:

```sh
# Run a command
cloud command:run "php artisan migrate"

# List previously run commands
cloud command:list [environment]

# Get command output and status
cloud command:get [command-id]
```

### Diagnostics

```sh
# Application health overview
cloud status

# Detailed diagnostics (environment status, recent deployments, databases, caches)
cloud debug

# Generate CI/CD workflow configuration
cloud ci:setup
```

### Utility

```sh
# Open your application in the browser
cloud browser

# Open the Laravel Cloud dashboard for your app
cloud dashboard

# Get Laravel Cloud IP addresses (for firewall whitelisting)
cloud ip:addresses

# List dedicated clusters
cloud dedicated-cluster:list

# Generate and install shell completions
cloud completions
```

## Command aliases

Common commands have short aliases:

| Alias | Command |
|-------|---------|
| `apps` | `application:list` |
| `envs` | `environment:list` |
| `vars` | `environment:variables` |
| `logs` | `environment:logs` |
| `status` | `status` |

## Configuration

| File | Purpose |
|------|---------|
| `~/.config/cloud/config.json` | Auth tokens and user preferences |
| `.cloud/config.json` (project root) | Default application and environment for the current repo |

## Shell completions

Enable tab completion for all commands:

```sh
cloud completions
```

Follow the prompts to install for your shell (Bash, Zsh, or Fish).
