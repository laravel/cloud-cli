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
