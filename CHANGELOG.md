# Release Notes

## [Unreleased](https://github.com/laravel/cloud-cli/compare/v0.6.0...main)

## [v0.6.0](https://github.com/laravel/cloud-cli/compare/v0.5.3...v0.6.0) - 2026-09-04

### What's Changed

* Support GitLab as a source provider by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/217
* Support Bitbucket as a source provider by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/218
* Bound the site-readiness poll in ship and survive connection failures by [@usamamuneerchaudhary](https://github.com/usamamuneerchaudhary) in https://github.com/laravel/cloud-cli/pull/216
* Drop several fields that are ultimately unused by the API by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/219

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.5.3...v0.6.0

## [v0.5.3](https://github.com/laravel/cloud-cli/compare/v0.5.2...v0.5.3) - 2026-09-03

### What's Changed

* Package updates by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/199
* Mask API tokens in auth:token --list by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/200
* Replace `selectWithContext` with `select` by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/201
* Dropped handrolled notification for Prompts by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/202
* Swap handrolled dynamic spinner component for Prompts task component by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/203
* Add Claude.md by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/204
* Add commands for managing secrets by [@ryangjchandler](https://github.com/ryangjchandler) in https://github.com/laravel/cloud-cli/pull/209
* fix database API compatibility by [@jewei](https://github.com/jewei) in https://github.com/laravel/cloud-cli/pull/205
* Add --root-directory support to application:create and ship by [@WendellAdriel](https://github.com/WendellAdriel) in https://github.com/laravel/cloud-cli/pull/208
* Fix bucket key secret never being returned by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/211
* Document managed-queue, tinker and skills:install commands in README by [@usamamuneerchaudhary](https://github.com/usamamuneerchaudhary) in https://github.com/laravel/cloud-cli/pull/210
* Fix database-snapshot:get and :delete hitting a 404 by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/212
* Replace the dead env var replace action with delete by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/213
* Add the missing stopped websocket server status by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/214
* Prepare CLI to be released with Bosun by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/215

### New Contributors

* [@ryangjchandler](https://github.com/ryangjchandler) made their first contribution in https://github.com/laravel/cloud-cli/pull/209
* [@jewei](https://github.com/jewei) made their first contribution in https://github.com/laravel/cloud-cli/pull/205
* [@WendellAdriel](https://github.com/WendellAdriel) made their first contribution in https://github.com/laravel/cloud-cli/pull/208
* [@usamamuneerchaudhary](https://github.com/usamamuneerchaudhary) made their first contribution in https://github.com/laravel/cloud-cli/pull/210

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.5.2...v0.5.3

## [v0.5.2](https://github.com/laravel/cloud-cli/compare/v0.5.1...v0.5.2) - 2026-08-18

### What's Changed

* Read the API token from LARAVEL_CLOUD_TOKEN and make auth:token scriptable by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/197
* Fix self-update 404 by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/198

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.5.1...v0.5.2

## [v0.5.1](https://github.com/laravel/cloud-cli/compare/v0.5.0...v0.5.1) - 2026-08-18

### What's Changed

* Add Dependabot cooldown of 5 days by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/cloud-cli/pull/166
* Enable Dependabot auto-merge by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/cloud-cli/pull/167
* Bump actions/checkout from 6.0.2 to 6.0.3 in the github-actions group by [@dependabot](https://github.com/dependabot)[bot] in https://github.com/laravel/cloud-cli/pull/169
* Bump shivammathur/setup-php from 2.37.1 to 2.37.2 in the github-actions group by [@dependabot](https://github.com/dependabot)[bot] in https://github.com/laravel/cloud-cli/pull/170
* Bump actions/checkout from 6.0.3 to 7.0.0 in the github-actions group by [@dependabot](https://github.com/dependabot)[bot] in https://github.com/laravel/cloud-cli/pull/171
* Bump actions/checkout from 7.0.0 to 7.0.1 in the github-actions group by [@dependabot](https://github.com/dependabot)[bot] in https://github.com/laravel/cloud-cli/pull/176
* Reduce PHAR size with new build script by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/180
* Fix missing corners on header-less tables by [@WaleedHU](https://github.com/WaleedHU) in https://github.com/laravel/cloud-cli/pull/178
* Upgrade to Laravel 13 by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/181
* Rename hibernation to scale to zero across user-facing output by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/184
* Respect CLOUD_BASE_URL in built binaries by [@fideloper](https://github.com/fideloper) in https://github.com/laravel/cloud-cli/pull/182
* Fix static analysis workflow by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/185
* Mask credentials in JSON output, not just environment variables by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/186
* Fix database-restore:create fataling on every invocation by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/187
* Fix database-cluster:update ignoring every option by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/188
* Fix instance:create scaling type handling by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/189
* Include database, cache and websocket relationships when fetching environments by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/190
* Register prompt fallbacks for our custom prompts so they work on Windows by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/191
* Make repo:config work non-interactively by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/192
* Scope form fields per resource, and stop retrying requests that cannot succeed by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/193
* Fail with a readable error when the API sends back something that is not JSON by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/194
* Add merged PRs to the release script by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/195

### New Contributors

* [@WaleedHU](https://github.com/WaleedHU) made their first contribution in https://github.com/laravel/cloud-cli/pull/178
* [@fideloper](https://github.com/fideloper) made their first contribution in https://github.com/laravel/cloud-cli/pull/182

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.5.0...v0.5.1

## [v0.5.0](https://github.com/laravel/cloud-cli/compare/v0.4.2...v0.5.0) - 2026-06-01

### What's Changed

* Bump shivammathur/setup-php from 2.37.0 to 2.37.1 in the github-actions group across 1 directory by [@dependabot](https://github.com/dependabot)[bot] in https://github.com/laravel/cloud-cli/pull/161
* Add managed queue commands by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/164

### New Contributors

* [@dependabot](https://github.com/dependabot)[bot] made their first contribution in https://github.com/laravel/cloud-cli/pull/161

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.4.2...v0.5.0

## [v0.4.2](https://github.com/laravel/cloud-cli/compare/v0.4.1...v0.4.2) - 2026-05-18

### What's Changed

* Fix ASCII art alignment by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/159
* Fix command history spinner by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/160
* Pin GitHub Actions to commit SHAs and add Dependabot config by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/156

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.4.1...v0.4.2

## [v0.4.1](https://github.com/laravel/cloud-cli/compare/v0.4.0...v0.4.1) - 2026-05-16

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.4.0...v0.4.1

## [v0.4.0](https://github.com/laravel/cloud-cli/compare/v0.3.0...v0.4.0) - 2026-05-16

### What's Changed

* Add --history flag to command:run by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/157
* Autocomplete artisan commands in command:run prompt by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/158

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.3.0...v0.4.0

## [v0.3.0](https://github.com/laravel/cloud-cli/compare/v0.2.6...v0.3.0) - 2026-04-30

### What's Changed

* feat(billing): add usage command by [@Frostist](https://github.com/Frostist) in https://github.com/laravel/cloud-cli/pull/153

### New Contributors

* [@Frostist](https://github.com/Frostist) made their first contribution in https://github.com/laravel/cloud-cli/pull/153

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.2.6...v0.3.0

## [v0.2.6](https://github.com/laravel/cloud-cli/compare/v0.2.5...v0.2.6) - 2026-04-28

### What's Changed

* Mask environment variable values in JSON output by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/155

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.2.5...v0.2.6

## [v0.2.5](https://github.com/laravel/cloud-cli/compare/v0.2.4...v0.2.5) - 2026-04-21

### What's Changed

* Attach database, cache, and WebSocket app via environment:update by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/147
* Accept bucket arg on bucket-key:delete and bucket-key:update by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/148
* Return SUCCESS when list commands find no results by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/149
* Fix websocket-cluster:create in non-interactive mode by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/150
* Fix websocket-application:create in non-interactive mode by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/151
* Fix instance:create in non-interactive mode by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/152

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.2.4...v0.2.5

## [v0.2.4](https://github.com/laravel/cloud-cli/compare/v0.2.3...v0.2.4) - 2026-04-20

### What's Changed

* Removed redundant null coalesce by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/145
* Added install skills middleware by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/146

**Full Changelog**: https://github.com/laravel/cloud-cli/compare/v0.2.3...v0.2.4

## [v0.2.3](https://github.com/laravel/cloud-cli/compare/v0.2.2...v0.2.3) - 2026-04-20

* Move all packages from `require` to `require-dev` to avoid conflicts on installation by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/144

## [v0.2.2](https://github.com/laravel/cloud-cli/compare/v0.2.1...v0.2.2) - 2026-04-10

* Add `skills:install` command by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/143

## [v0.2.1](https://github.com/laravel/cloud-cli/compare/v0.2.0...v0.2.1) - 2026-04-10

* Allow `tinker` to run non-interactively by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/142

## [v0.2.0](https://github.com/laravel/cloud-cli/compare/v0.1.18...v0.2.0) - 2026-04-10

* Add `tinker` Command by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/141
* [main] [bug] Catch RequestException instead of Throwable in DatabaseDelete by [@JoshSalway](https://github.com/JoshSalway) in https://github.com/laravel/cloud-cli/pull/138
* [main] [cleanup] Remove commented-out dead code from EnvironmentVariables by [@JoshSalway](https://github.com/JoshSalway) in https://github.com/laravel/cloud-cli/pull/137
* [main] [bug] Guard against empty token list in auth:token --remove by [@JoshSalway](https://github.com/JoshSalway) in https://github.com/laravel/cloud-cli/pull/134
* [main] [bug] Use self::FAILURE constant in InstanceDelete by [@JoshSalway](https://github.com/JoshSalway) in https://github.com/laravel/cloud-cli/pull/133
* [main] [bug] Remove credential leak in database:open by [@JoshSalway](https://github.com/JoshSalway) in https://github.com/laravel/cloud-cli/pull/132
* [main] [bug] Fix method typo getWorkerDefult in BackgroundProcessCreate by [@JoshSalway](https://github.com/JoshSalway) in https://github.com/laravel/cloud-cli/pull/131
* [main] [bug] Fix Saloon RequestException import in 7 delete commands by [@JoshSalway](https://github.com/JoshSalway) in https://github.com/laravel/cloud-cli/pull/104

## [v0.1.18](https://github.com/laravel/cloud-cli/compare/v0.1.17...v0.1.18) - 2026-04-03

* Tell non-interactive users that deploy command is switching to ship by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/129

## [v0.1.17](https://github.com/laravel/cloud-cli/compare/v0.1.16...v0.1.17) - 2026-04-03

* Optimize CLI for AI agents in non-interactive mode by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/118
* Fix Font::load crash on Windows by [@JoshSalway](https://github.com/JoshSalway) in https://github.com/laravel/cloud-cli/pull/111
* De-dupe tokens on successful auth by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/119
* [main] [bug] Fix browser and file manager commands on Windows and Linux by [@JoshSalway](https://github.com/JoshSalway) in https://github.com/laravel/cloud-cli/pull/117
* Fix TypeError when startedAt is null in monitor renderers by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/120
* Global `--fields` option to reduce `--json` payload by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/121
* Monitor commands non-interactively by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/122
* Better confirm destructive in non interactive mode by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/123
* Include sent values for errors in non-interactive mode by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/124
* Update `deploy` description to point towards `ship` for new apps by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/125
* Added aliases for both human and agent friendly, guessable command names by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/126
* Fix: undefined index on IP Address table by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/127
* `deploy` ships in non-interactive mode if no app exists by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/128

## [v0.1.16](https://github.com/laravel/cloud-cli/compare/v0.1.15...v0.1.16) - 2026-03-30

* Upgrade saloonphp/saloon from v3 to v4 by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/cloud-cli/pull/116

## [v0.1.15](https://github.com/laravel/cloud-cli/compare/v0.1.14...v0.1.15) - 2026-03-16

* Fixed Safari auth flow by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/59

## [v0.1.14](https://github.com/laravel/cloud-cli/compare/v0.1.13...v0.1.14) - 2026-03-12

* Send CLI version by [@mateusjatenee](https://github.com/mateusjatenee) in https://github.com/laravel/cloud-cli/pull/19
* Don't require an auth token for completions by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/20
* Better non-interactive mode detection by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/21

## [v0.1.13](https://github.com/laravel/cloud-cli/compare/v0.1.12...v0.1.13) - 2026-03-11

* Makes imports consistent by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/cloud-cli/pull/17
* PHPStan and CI flow by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/18

## [v0.1.12](https://github.com/laravel/cloud-cli/compare/v0.1.11...v0.1.12) - 2026-03-10

* Detect agent and terminal by [@mateusjatenee](https://github.com/mateusjatenee) in https://github.com/laravel/cloud-cli/pull/12
* Composer update by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/15
* Fix: RepoConfig select LazyCollection error by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/cloud-cli/pull/16

## [v0.1.11](https://github.com/laravel/cloud-cli/compare/v0.1.10...v0.1.11) - 2026-03-02

## [v0.1.10](https://github.com/laravel/cloud-cli/compare/v0.1.9...v0.1.10) - 2026-03-02

## [v0.1.9](https://github.com/laravel/cloud-cli/compare/v0.1.8...v0.1.9) - 2026-03-02

## [v0.1.8](https://github.com/laravel/cloud-cli/compare/v0.1.7...v0.1.8) - 2026-03-02

## [v0.1.7](https://github.com/laravel/cloud-cli/compare/v0.1.6...v0.1.7) - 2026-03-02

## [v0.1.6](https://github.com/laravel/cloud-cli/compare/v0.1.5...v0.1.6) - 2026-03-02

## [v0.1.5](https://github.com/laravel/cloud-cli/compare/v0.1.4...v0.1.5) - 2026-03-02

## [v0.1.4](https://github.com/laravel/cloud-cli/compare/v0.1.3...v0.1.4) - 2026-03-02

## [v0.1.3](https://github.com/laravel/cloud-cli/compare/v0.1.2...v0.1.3) - 2026-03-02

## v0.1.2 - 2026-03-02

Initial release.
