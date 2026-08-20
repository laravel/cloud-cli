# Cloud CLI

Laravel Zero CLI for managing apps on [Laravel Cloud](https://cloud.laravel.com). PHP 8.3+. Run locally with `php cloud <command>`.

## Commands to run

```sh
vendor/bin/pest                  # tests
vendor/bin/pest --filter=Cache   # single test
vendor/bin/pint --dirty          # format (run after editing PHP)
vendor/bin/phpstan analyse       # static analysis, level 5
```

Point at a local API with `CLOUD_BASE_URL` in `.env`. `LARAVEL_CLOUD_TOKEN` overrides stored tokens.

## Layout

| Path | What |
| --- | --- |
| `app/Commands/` | One class per command, all extend `BaseCommand` |
| `app/Client/` | Saloon SDK for the Cloud API — see `app/Client/README.md` |
| `app/Client/Resources/<Thing>Resource.php` | Method-per-endpoint facade over the request classes |
| `app/Client/Requests/*RequestData.php` | Typed request payloads (spatie/laravel-data) |
| `app/Dto/` | Typed API responses; also drive `--json` output and `--fields` |
| `app/Resolvers/` | Turn a user-supplied ID/name into a DTO, prompting when interactive |
| `app/Prompts/` | Custom Laravel Prompts renderers (tables, monitors, slide-ins) |
| `app/Support/Form.php` | Merges options/arguments with prompts for mixed interactive/flag input |
| `app/Middleware/` | Command middleware (auth, JSON output suppression), registered in `AppServiceProvider` |
| `app/helpers.php` | Global output helpers: `answered()`, `success()`, `dataList()`, `dataTable()`, `codeBlock()` |
| `app/Git.php` | All git and `gh` CLI work |
| `app/ConfigRepository.php` | User config at `~/.config/cloud/config.json` |

## Conventions

- Every command supports `--json`, `--fields`, `--show-sensitive`, and `--no-interaction`. `BaseCommand::wantsJson()` is true when `--json` is passed *or* the run is non-interactive, so a command must work headlessly.
- Set `$jsonDataClass` (and `$jsonDataIsCollection`) on a command so `--json` and the help text know the field list.
- Emit JSON with `outputJsonIfWanted()` (exits) or `writeJsonIfWanted()` (continues) before any human-facing output.
- Never use `Command` output helpers (`$this->info()` etc.) — use Laravel Prompts functions and the helpers in `app/helpers.php`.
- Destructive commands call `confirmDestructive()`; it requires `--force` when non-interactive.
- Fetch through `spin(...)`, resolve arguments through `$this->resolvers()`, prompt through `$this->form()->prompt()`.
- Non-public members are `protected`, never `private`. Empty constructor bodies get a single `//` comment.
- Long signatures go one option per line, indented.
- Comments explain *why*, never *what*.

## Adding a command

1. Request class in `app/Client/Resources/<Thing>/`, payload in `app/Client/Requests/` if it takes a body.
2. Method on the matching `*Resource.php`; DTO in `app/Dto/` for the response.
3. Command in `app/Commands/`; Laravel Zero autoloads it, no registration needed.
4. Test in `tests/Feature/` using Saloon's `MockClient` and `Prompt::fake()` — see `tests/Feature/ApplicationCommandsTest.php` and the fixtures in `tests/Helpers.php`.

## Related docs

- `AI_CLI_DESIGN_PRINCIPLES.md` — why the CLI is shaped for agents as well as people
- `skills/deploying-laravel-cloud/` — the skill `cloud skills:install` ships to users
- API reference: https://cloud.laravel.com/docs/api/introduction
