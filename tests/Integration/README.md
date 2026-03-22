# Integration Tests

These tests hit the **real Laravel Cloud API**. They are skipped by default.

## Prerequisites

- A valid Laravel Cloud API token
- At least one application in your Cloud account (for read-only tests)

## Running Read-Only Tests

Read-only tests list resources and verify command output. They do not create, modify, or delete anything.

```bash
CLOUD_CLI_INTEGRATION=true \
LARAVEL_CLOUD_API_TOKEN=<your-token> \
vendor/bin/pest tests/Integration/
```

Or run only the read-only group:

```bash
CLOUD_CLI_INTEGRATION=true \
LARAVEL_CLOUD_API_TOKEN=<your-token> \
vendor/bin/pest tests/Integration/ --group=read-only
```

## Running Destructive Tests

Destructive tests create a temporary application, manipulate environment variables, stop/start environments, and then delete the application. **These tests may incur charges on your Cloud account.**

```bash
CLOUD_CLI_INTEGRATION=true \
CLOUD_CLI_INTEGRATION_DESTRUCTIVE=true \
LARAVEL_CLOUD_API_TOKEN=<your-token> \
vendor/bin/pest tests/Integration/ --group=destructive
```

### Estimated Cost

- A temporary application is created and deleted within the test run.
- If the test completes normally, the app exists for less than a minute.
- If the test fails mid-way, the app may remain and accrue charges until manually deleted.
- Estimated cost: **< $0.10 USD** for a successful run (brief compute time on the smallest instance).
- Check your Cloud dashboard after a failed destructive run to ensure no orphaned resources remain.

## Environment Variables

| Variable | Required | Description |
|---|---|---|
| `CLOUD_CLI_INTEGRATION` | Yes | Set to `true` to enable integration tests |
| `LARAVEL_CLOUD_API_TOKEN` | Yes | Your Laravel Cloud API token |
| `CLOUD_CLI_INTEGRATION_DESTRUCTIVE` | No | Set to `true` to enable destructive lifecycle tests |

## Future: Full Command Coverage

Currently integration tests cover 12 of 98 commands (11 read-only + 1 lifecycle). Full coverage is blocked by the lack of a sandbox environment — every API call creates real infrastructure and incurs charges.

To safely test all 98 commands, Laravel Cloud would need one of:

- **Test API keys** (like Stripe's `sk_test_*`) that hit a sandbox with no billing
- **A dry-run mode at the API level** that validates requests and returns realistic responses without provisioning resources
- **A dedicated test organization** with a billing cap or free tier for CI testing

With any of these, the integration suite could be expanded to cover every command — including database cluster creation, cache provisioning, websocket clusters, background processes, remote command execution, and snapshot/restore workflows — without cost or risk.

### Commands Not Yet Integration Tested

The following commands need real resource creation to test and are not covered:

**Database commands:** `database-cluster:create`, `database-cluster:update`, `database-cluster:delete`, `database:create`, `database:delete`, `database:open`, `database-snapshot:create`, `database-snapshot:delete`, `database-restore:create`

**Cache commands:** `cache:create`, `cache:update`, `cache:delete`

**WebSocket commands:** all 12 `websocket-cluster:*` and `websocket-application:*` commands

**Instance commands:** `instance:create`, `instance:update`, `instance:delete`

**Background process commands:** all 5 `background-process:*` commands

**Remote execution:** `command:run`, `command:list`, `command:get`

**Other:** `environment:create`, `environment:update`, `environment:delete`, `domain:create`, `domain:update`, `domain:delete`, `application:update`, `deploy:monitor`
