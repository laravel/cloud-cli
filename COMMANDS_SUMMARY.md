# CLI Commands Summary

This document lists all available CLI commands that correspond to the Laravel Cloud API endpoints.

## Applications

- `application:list` - List all applications
  - Options: `--json`

- `application:get {application}` - Get application details
  - Options: `--json`

- `application:update {application}` - Update an application
  - Options: `--name=`, `--slack-channel=`, `--json`

## Environments

- `environment:list {application?}` - List all environments for an application
  - Options: `--json`

- `environment:get {environment}` - Get environment details
  - Options: `--json`

- `environment:create {application}` - Create a new environment
  - Options: `--name=`, `--branch=`, `--json`

- `environment:update {environment}` - Update an environment
  - Options: `--branch=`, `--build-command=`, `--deploy-command=`, `--json`

- `environment:delete {environment}` - Delete an environment
  - Options: `--force`

## Instances

- `instance:list {environment}` - List all instances for an environment
  - Options: `--json`

- `instance:get {instance}` - Get instance details
  - Options: `--json`

- `instance:create {environment}` - Create a new instance
  - Options: `--name=`, `--type=`, `--size=`, `--min-replicas=`, `--max-replicas=`, `--json`

- `instance:update {instance}` - Update an instance
  - Options: `--size=`, `--min-replicas=`, `--max-replicas=`, `--scaling-type=`, `--json`

- `instance:delete {instance}` - Delete an instance
  - Options: `--force`

## Deployments

- `deployment:list {environment}` - List all deployments for an environment
  - Options: `--json`

- `deployment:get {deployment}` - Get deployment details
  - Options: `--json`

Note: `deploy` command already exists for initiating deployments.

## Domains

- `domain:list {environment}` - List all domains for an environment
  - Options: `--json`

- `domain:get {domain}` - Get domain details
  - Options: `--json`

- `domain:create {environment} {domain}` - Create a new domain
  - Options: `--json`

- `domain:update {domain}` - Update a domain
  - Options: `--is-primary=`, `--json`

- `domain:delete {domain}` - Delete a domain
  - Options: `--force`

## Commands

- `command:list {environment}` - List all commands for an environment
  - Options: `--json`

- `command:get {command}` - Get command details
  - Options: `--json`

- `command:run {environment} {command}` - Run a command on an environment
  - Options: `--json`

## Background Processes

- `background-process:list {instance}` - List all background processes for an instance
  - Options: `--json`

- `background-process:get {process}` - Get background process details
  - Options: `--json`

- `background-process:create {instance}` - Create a new background process
  - Options: `--command=`, `--type=`, `--instances=`, `--queue=`, `--connection=`, `--json`

- `background-process:update {process}` - Update a background process
  - Options: `--command=`, `--instances=`, `--json`

- `background-process:delete {process}` - Delete a background process
  - Options: `--force`

## Databases

- `database:list` - List all database clusters
  - Options: `--json`

- `database:get {database}` - Get database cluster details
  - Options: `--json`

## Network

- `ip:addresses` - Get Laravel Cloud IP addresses by region
  - Options: `--json`

## AI-Friendly Features

All commands include:

- **`--json` flag**: Output structured JSON for easy parsing
- **Non-interactive mode**: All inputs via command-line flags
- **Consistent patterns**: REST-like resource:action naming
- **Structured errors**: Clear validation error messages
- **`--force` flag**: Skip confirmations for delete operations

## Examples

```bash
# List all applications as JSON
cloud application:list --json

# Get environment details
cloud environment:get env_123

# Create a domain non-interactively
cloud domain:create env_123 example.com

# Run a command and get JSON output
cloud command:run env_123 "php artisan migrate" --json

# Delete without confirmation
cloud environment:delete env_123 --force
```
