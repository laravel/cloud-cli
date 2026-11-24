<?php

namespace App\Commands;

use App\Client\Requests\UpdateEnvironmentRequestData;
use App\Dto\BucketKey;
use App\Dto\Cache;
use App\Dto\Database;
use App\Dto\Environment;
use App\Dto\ObjectStorageBucket;
use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;
use App\Enums\EnvironmentColor;
use App\Exceptions\CommandExitException;
use App\Support\SensitiveValues;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class EnvironmentUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'environment:update
                            {environment? : The environment ID or name}
                            {--name= : Environment name}
                            {--slug= : Environment slug}
                            {--color= : Color (blue, green, orange, purple, red, yellow, cyan, gray)}
                            {--branch= : Git branch}
                            {--build-command= : Build command}
                            {--deploy-command= : Deploy command}
                            {--post-deploy-command= : Commands run after the deployment goes live}
                            {--start-command= : Start command for non-PHP dynamic apps (empty string to clear)}
                            {--php-version= : PHP version (8.2:1, 8.3:1, 8.4:1, 8.5:1)}
                            {--node-version= : Node version (20, 22, 24, 26)}
                            {--uses-push-to-deploy= : Deploy on push}
                            {--uses-deploy-hook= : Enable the deploy hook}
                            {--uses-vanity-domain= : Serve the Laravel Cloud domain}
                            {--uses-octane= : Uses Octane}
                            {--http-basic-auth-password= : Require visitors to sign in as `admin` with this password; pass an empty value to turn basic auth off}
                            {--uses-purge-edge-cache-on-deploy= : Purge the edge cache on deploy}
                            {--timeout= : Request timeout in seconds (5-60)}
                            {--shutdown-timeout= : Seconds to keep the previous deployment running (1-600)}
                            {--sleep-timeout= : Minutes before hibernating (1-60)}
                            {--hibernation-wake-up-interval= : Minutes between hibernation wake-ups (1-10080, empty string to disable)}
                            {--nightwatch-token= : Nightwatch token (empty string to remove)}
                            {--cache-strategy= : Edge cache strategy (default, bypass)}
                            {--response-headers-frame= : X-Frame-Options header (deny, sameorigin, all)}
                            {--response-headers-content-type= : X-Content-Type-Options header (nosniff, none)}
                            {--response-headers-robots-tag= : X-Robots-Tag header ("index, follow", "noindex, nofollow")}
                            {--hsts-max-age= : HSTS max age in seconds}
                            {--hsts-include-subdomains= : HSTS includeSubDomains}
                            {--hsts-preload= : HSTS preload}
                            {--firewall-block-path= : Block requests for paths such as .php, .env and .git}
                            {--firewall-browser-integrity-check= : Enable the browser integrity check}
                            {--database-id= : Database ID to attach (empty string to detach)}
                            {--cache-id= : Cache ID to attach (empty string to detach)}
                            {--websocket-application-id= : WebSocket application ID to attach (empty string to detach)}
                            {--model-gateway-key-id= : Model gateway key ID to attach, exposed as LARAVEL_CLOUD_MODEL_GATEWAY_API_KEY from the next deployment (empty string to detach)}
                            {--bucket-keys= : Bucket keys to attach as comma-separated KEY_ID:DISK entries, with :default on at most one (fk-1:local:default,fk-2:public). Replaces every attached bucket; empty string to detach all}
                            {--force : Force update without confirmation}';

    protected $description = 'Update an environment';

    protected $aliases = ['env:update'];

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Environment');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $this->defineFields($environment);

        foreach ($this->form()->filled() as $key => $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                SensitiveValues::isSensitiveKey($key) && filled($value->value()) ? SensitiveValues::MASK : $value->value(),
            );
        }

        $updatedEnvironment = $this->runUpdate(
            fn () => $this->updateEnvironment($environment),
            fn () => $this->collectDataAndUpdate($environment),
        );

        $this->outputJsonIfWanted($updatedEnvironment);

        success("Environment updated: {$updatedEnvironment->name}");
    }

    protected function updateEnvironment(Environment $environment): Environment
    {
        $filesystemKeys = $this->filesystemKeys($environment);

        // The API turns basic auth on only alongside a password, so one option covers both: a password turns it on, empty turns it off.
        $httpBasicAuthPassword = $this->form()->get('http_basic_auth_password');

        spin(
            fn () => $this->client->environments()->update(
                new UpdateEnvironmentRequestData(
                    environmentId: $environment->id,
                    name: $this->form()->get('name'),
                    slug: $this->form()->get('slug'),
                    color: $this->form()->get('color'),
                    branch: $this->form()->get('branch'),
                    usesPushToDeploy: $this->form()->boolean('uses_push_to_deploy'),
                    usesDeployHook: $this->form()->boolean('uses_deploy_hook'),
                    timeout: $this->form()->integer('timeout'),
                    phpVersion: $this->form()->get('php_version'),
                    buildCommand: $this->form()->get('build_command'),
                    nodeVersion: $this->form()->get('node_version'),
                    deployCommand: $this->form()->get('deploy_command'),
                    postDeployCommand: $this->form()->get('post_deploy_command'),
                    startCommand: $this->form()->get('start_command'),
                    usesVanityDomain: $this->form()->boolean('uses_vanity_domain'),
                    databaseSchemaId: $this->form()->get('database_id'),
                    cacheId: $this->form()->get('cache_id'),
                    websocketApplicationId: $this->form()->get('websocket_application_id'),
                    modelGatewayKeyId: $this->form()->get('model_gateway_key_id'),
                    usesOctane: $this->form()->boolean('uses_octane'),
                    sleepTimeout: $this->form()->integer('sleep_timeout'),
                    hibernationWakeUpInterval: $this->clearableInteger('hibernation_wake_up_interval'),
                    shutdownTimeout: $this->form()->integer('shutdown_timeout'),
                    usesPurgeEdgeCacheOnDeploy: $this->form()->boolean('uses_purge_edge_cache_on_deploy'),
                    nightwatchToken: $this->form()->get('nightwatch_token'),
                    cacheStrategy: $this->form()->get('cache_strategy'),
                    responseHeadersFrame: $this->form()->get('response_headers_frame'),
                    responseHeadersContentType: $this->form()->get('response_headers_content_type'),
                    responseHeadersRobotsTag: $this->form()->get('response_headers_robots_tag'),
                    responseHeadersHsts: $this->hsts($environment),
                    firewallBlockPath: $this->form()->boolean('firewall_block_path'),
                    firewallBrowserIntegrityCheck: $this->form()->boolean('firewall_browser_integrity_check'),
                    filesystemKeys: $filesystemKeys,
                    usesHttpBasicAuth: $httpBasicAuthPassword === null ? null : filled($httpBasicAuthPassword),
                    httpBasicAuthPassword: filled($httpBasicAuthPassword) ? $httpBasicAuthPassword : null,
                ),
            ),
            'Updating environment...',
        );

        return $this->client->environments()->get($environment->id);
    }

    /**
     * The API takes the attached buckets as one list and replaces it, so the option names every bucket the environment should end up with.
     *
     * @return array<int, array{id: string, disk: string, is_default_disk: bool}>|null
     */
    protected function filesystemKeys(Environment $environment): ?array
    {
        $value = $this->form()->get('filesystem_keys');

        if ($value === null) {
            return null;
        }

        $keys = $this->parseBucketKeys($value);

        if ($environment->bucketIds !== []) {
            $this->confirmDestructive($keys === []
                ? "Detach every bucket from {$environment->name}?"
                : "Replace the buckets attached to {$environment->name}? Any attached bucket not listed will be detached.");
        }

        return $keys;
    }

    /**
     * @return array<int, array{id: string, disk: string, is_default_disk: bool}>
     */
    protected function parseBucketKeys(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $keys = collect(explode(',', $value))->map(function (string $entry) {
            $parts = array_map(trim(...), explode(':', $entry));

            if (count($parts) < 2 || count($parts) > 3 || $parts[0] === '' || $parts[1] === '' || (isset($parts[2]) && $parts[2] !== 'default')) {
                $this->failAndExit("Invalid bucket key entry: {$entry}. Use KEY_ID:DISK, or KEY_ID:DISK:default for the default disk.");
            }

            return ['id' => $parts[0], 'disk' => $parts[1], 'is_default_disk' => isset($parts[2])];
        });

        if ($keys->where('is_default_disk', true)->count() > 1) {
            $this->failAndExit('Only one bucket can be the default disk.');
        }

        return $keys->values()->all();
    }

    protected function clearableInteger(string $key): int|string|null
    {
        $value = $this->form()->get($key);

        return $value === null || $value === '' ? $value : (int) $value;
    }

    /**
     * The API only accepts all three HSTS settings together, so any left out keep their current value.
     *
     * @return array{max_age: int|null, include_subdomains: bool, preload: bool}|null
     */
    protected function hsts(Environment $environment): ?array
    {
        $maxAge = $this->form()->get('hsts_max_age');
        $includeSubdomains = $this->form()->boolean('hsts_include_subdomains');
        $preload = $this->form()->boolean('hsts_preload');

        if ($maxAge === null && $includeSubdomains === null && $preload === null) {
            return null;
        }

        $current = data_get($environment->networkSettings, 'response_headers.hsts', []);

        return [
            'max_age' => $maxAge !== null && $maxAge !== '' ? (int) $maxAge : ($current['max_age'] ?? null),
            'include_subdomains' => $includeSubdomains ?? (bool) ($current['include_subdomains'] ?? false),
            'preload' => $preload ?? (bool) ($current['preload'] ?? false),
        ];
    }

    protected function defineFields(Environment $environment): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Name',
                    default: $value ?? $environment->name,
                    required: true,
                ),
            ),
        )->setPreviousValue($environment->name);

        $this->form()->define(
            'slug',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Slug',
                    default: $value ?? $environment->slug,
                    required: true,
                ),
            ),
        )->setPreviousValue($environment->slug);

        $this->defineSelect('color', 'Color', collect(EnvironmentColor::cases())->mapWithKeys(
            fn (EnvironmentColor $color) => [$color->value => $color->dot().' '.ucfirst($color->value)],
        )->all(), $environment->color);

        $this->form()->define(
            'branch',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Branch',
                    default: $value ?? $environment->branch ?? '',
                ),
            ),
        )->setPreviousValue($environment->branch ?? '');

        $this->form()->define(
            'build_command',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => textarea(
                    label: 'Build command',
                    default: $value ?? $environment->buildCommand ?? '',
                ),
            ),
            'build-command',
        )->setPreviousValue($environment->buildCommand ?? '');

        $this->form()->define(
            'deploy_command',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => textarea(
                    label: 'Deploy command',
                    default: $value ?? $environment->deployCommand ?? '',
                ),
            ),
            'deploy-command',
        )->setPreviousValue($environment->deployCommand ?? '');

        $this->form()->define(
            'post_deploy_command',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => textarea(
                    label: 'Post-deploy command',
                    default: $value ?? $environment->postDeployCommand ?? '',
                ),
            ),
            'post-deploy-command',
        )->setPreviousValue($environment->postDeployCommand ?? '');

        $this->form()->define(
            'start_command',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Start command',
                    default: $value ?? $environment->startCommand ?? '',
                    hint: 'Leave empty to clear',
                ),
            ),
        )->setPreviousValue($environment->startCommand ?? '');

        $this->defineSelect('php_version', 'PHP version', [
            '8.2:1' => 'PHP 8.2',
            '8.3:1' => 'PHP 8.3',
            '8.4:1' => 'PHP 8.4',
            '8.5:1' => 'PHP 8.5',
        ], $environment->phpMajorVersion ? "{$environment->phpMajorVersion}:1" : null);

        $this->defineSelect('node_version', 'Node version', [
            '20' => 'Node 20',
            '22' => 'Node 22',
            '24' => 'Node 24',
            '26' => 'Node 26',
        ], $environment->nodeVersion);

        $this->defineBoolean('uses_push_to_deploy', 'Push to deploy', $environment->usesPushToDeploy);
        $this->defineBoolean('uses_deploy_hook', 'Deploy hook', $environment->usesDeployHook);
        $this->defineBoolean('uses_vanity_domain', 'Vanity domain', null);
        $this->defineBoolean('uses_octane', 'Octane', $environment->usesOctane);
        $this->form()->define(
            'http_basic_auth_password',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(label: 'HTTP basic auth?', default: $environment->usesHttpBasicAuth)
                    ? password(label: 'HTTP basic auth password', required: true, hint: 'Visitors sign in as `admin`')
                    : '',
            ),
        )->setPreviousValue(var_export($environment->usesHttpBasicAuth, true))->setLabel('HTTP basic auth');

        $this->defineBoolean('uses_purge_edge_cache_on_deploy', 'Purge edge cache on deploy', null);

        $this->defineNumber('timeout', 'Timeout (seconds)', 5, 60, $environment->timeout);
        $this->defineNumber('shutdown_timeout', 'Shutdown timeout (seconds)', 1, 600, $environment->shutdownTimeout);
        $this->defineNumber('sleep_timeout', 'Sleep timeout (minutes)', 1, 60, null);

        $this->form()->define(
            'hibernation_wake_up_interval',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Hibernation wake-up interval (minutes)',
                    default: (string) ($value ?? $environment->hibernationWakeUpInterval ?? ''),
                    validate: fn ($input) => $input === '' || (ctype_digit($input) && $input >= 1 && $input <= 10080)
                        ? null
                        : 'Enter a number of minutes between 1 and 10080.',
                    hint: 'Leave empty to disable',
                ),
            ),
        )->setPreviousValue((string) ($environment->hibernationWakeUpInterval ?? ''))->setLabel('Hibernation wake-up interval');

        $this->form()->define(
            'nightwatch_token',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => password(
                    label: 'Nightwatch token',
                    hint: 'Leave empty to remove',
                ),
            ),
        );

        $this->defineSelect('cache_strategy', 'Edge cache strategy', [
            'default' => 'Default',
            'bypass' => 'Bypass',
        ], data_get($environment->networkSettings, 'cache.strategy'));

        $this->defineSelect('response_headers_frame', 'X-Frame-Options', [
            'deny' => 'Deny',
            'sameorigin' => 'Same origin',
            'all' => 'All',
        ], data_get($environment->networkSettings, 'response_headers.frame'));

        $this->defineSelect('response_headers_content_type', 'X-Content-Type-Options', [
            'nosniff' => 'nosniff',
            'none' => 'None',
        ], data_get($environment->networkSettings, 'response_headers.content_type'));

        $this->defineSelect('response_headers_robots_tag', 'X-Robots-Tag', [
            'index, follow' => 'index, follow',
            'noindex, nofollow' => 'noindex, nofollow',
        ], data_get($environment->networkSettings, 'response_headers.robots_tag'));

        $currentMaxAge = data_get($environment->networkSettings, 'response_headers.hsts.max_age');

        $this->form()->define(
            'hsts_max_age',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'HSTS max age (seconds)',
                    default: (string) ($value ?? $currentMaxAge ?? ''),
                    min: 0,
                ),
            ),
        )->setPreviousValue($currentMaxAge === null ? null : (string) $currentMaxAge)->setLabel('HSTS max age');

        $this->defineBoolean('hsts_include_subdomains', 'HSTS include subdomains', data_get($environment->networkSettings, 'response_headers.hsts.include_subdomains'));
        $this->defineBoolean('hsts_preload', 'HSTS preload', data_get($environment->networkSettings, 'response_headers.hsts.preload'));
        $this->defineBoolean('firewall_block_path', 'Firewall block path', data_get($environment->networkSettings, 'firewall.block_path'));
        $this->defineBoolean('firewall_browser_integrity_check', 'Firewall browser integrity check', null);

        $this->form()->define(
            'database_id',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->selectDatabase($value, $environment->databaseSchemaId),
            ),
            'database-id',
        )->setPreviousValue($environment->databaseSchemaId ?? '');

        $this->form()->define(
            'cache_id',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->selectCache($value, $environment->cacheId),
            ),
            'cache-id',
        )->setPreviousValue($environment->cacheId ?? '');

        $this->form()->define(
            'websocket_application_id',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->selectWebsocketApplication($value, $environment->websocketApplicationId),
            ),
            'websocket-application-id',
        )->setPreviousValue($environment->websocketApplicationId ?? '');

        // The API has no endpoint that lists model gateway keys, so there is nothing to pick from.
        $this->form()->define(
            'model_gateway_key_id',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Model gateway key ID',
                    default: $value ?? $environment->modelGatewayKeyId ?? '',
                    hint: 'Leave empty to detach',
                ),
            ),
            'model-gateway-key-id',
        )->setPreviousValue($environment->modelGatewayKeyId ?? '')->setLabel('Model gateway key');

        // Named after the API field so a validation error on filesystem_keys.* asks for the buckets again.
        $this->form()->define(
            'filesystem_keys',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->selectBucketKeys(),
            ),
            'bucket-keys',
        )->setLabel('Buckets');
    }

    /**
     * @param  array<int|string, string>  $options
     */
    protected function defineSelect(string $key, string $label, array $options, ?string $current): void
    {
        $this->form()->define(
            $key,
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => (string) select(
                    label: $label,
                    options: $options,
                    default: $value ?? (array_key_exists((string) $current, $options) ? $current : null),
                ),
            ),
        )->setPreviousValue($current)->setLabel($label);
    }

    protected function defineBoolean(string $key, string $label, ?bool $current): void
    {
        $this->form()->define(
            $key,
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: "{$label}?",
                    default: $current ?? false,
                ),
            ),
        )->setPreviousValue($current === null ? null : var_export($current, true))->setLabel($label);
    }

    protected function defineNumber(string $key, string $label, int $min, int $max, ?int $current): void
    {
        $this->form()->define(
            $key,
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => (int) number(
                    label: $label,
                    default: (string) ($value ?? $current ?? ''),
                    min: $min,
                    max: $max,
                ),
            ),
        )->setPreviousValue($current === null ? null : (string) $current)->setLabel($label);
    }

    protected function selectDatabase(?string $value, ?string $currentId): string
    {
        $clusters = spin(
            fn () => $this->client->databaseClusters()->include('databases')->list()->collect(),
            'Fetching databases...',
        );

        $clustersWithSchemas = $clusters->filter(fn ($cluster) => ! empty($cluster->schemas))->values();

        if ($clustersWithSchemas->isEmpty()) {
            $this->failAndExit('No databases found. Create one with `cloud database-cluster:create` and `cloud database:create`.');
        }

        $currentCluster = $clustersWithSchemas->first(
            fn ($cluster) => collect($cluster->schemas)->contains(fn (Database $schema) => $schema->id === $currentId),
        );

        if ($clustersWithSchemas->count() === 1) {
            $selectedCluster = $clustersWithSchemas->first();
        } else {
            $clusterOptions = $currentId ? ['' => '— Detach —'] : [];

            foreach ($clustersWithSchemas as $cluster) {
                $clusterOptions[$cluster->id] = $cluster->name;
            }

            $selectedClusterId = (string) select(
                label: 'Database cluster',
                options: $clusterOptions,
                default: $currentCluster !== null ? $currentCluster->id : '',
            );

            if ($selectedClusterId === '') {
                return '';
            }

            $selectedCluster = $clustersWithSchemas->firstWhere('id', $selectedClusterId);
        }

        $options = $currentId ? ['' => '— Detach —'] : [];

        foreach ($selectedCluster->schemas as $schema) {
            /** @var Database $schema */
            $options[$schema->id] = $schema->id === $currentId ? "{$schema->name} (current)" : $schema->name;
        }

        return (string) select(
            label: 'Database to attach',
            options: $options,
            default: $value ?? ($selectedCluster->id === $currentCluster?->id ? $currentId : null) ?? '',
        );
    }

    protected function selectCache(?string $value, ?string $currentId): string
    {
        $caches = spin(
            fn () => $this->client->caches()->list()->collect(),
            'Fetching caches...',
        );

        if ($caches->isEmpty()) {
            $this->failAndExit('No caches found. Create one with `cloud cache:create`.');
        }

        $options = $currentId ? ['' => '— Detach —'] : [];

        foreach ($caches as $cache) {
            /** @var Cache $cache */
            $options[$cache->id] = $cache->id === $currentId ? "{$cache->name} (current)" : $cache->name;
        }

        return (string) select(
            label: 'Cache to attach',
            options: $options,
            default: $value ?? $currentId ?? '',
        );
    }

    protected function selectWebsocketApplication(?string $value, ?string $currentId): string
    {
        $clusters = spin(
            fn () => $this->client->websocketClusters()->list()->collect(),
            'Fetching WebSocket clusters...',
        );

        if ($clusters->isEmpty()) {
            $this->failAndExit('No WebSocket clusters found. Create one with `cloud websocket-cluster:create` and `cloud websocket-application:create`.');
        }

        $currentCluster = $clusters->first(
            fn (WebsocketCluster $cluster) => in_array($currentId, $cluster->applicationIds, true),
        );

        if ($clusters->count() === 1) {
            $selectedCluster = $clusters->first();
        } else {
            $clusterOptions = $currentId ? ['' => '— Detach —'] : [];

            foreach ($clusters as $cluster) {
                /** @var WebsocketCluster $cluster */
                $clusterOptions[$cluster->id] = $cluster->name;
            }

            $selectedClusterId = (string) select(
                label: 'WebSocket cluster',
                options: $clusterOptions,
                default: $currentCluster !== null ? $currentCluster->id : '',
            );

            if ($selectedClusterId === '') {
                return '';
            }

            $selectedCluster = $clusters->firstWhere('id', $selectedClusterId);
        }

        $apps = spin(
            fn () => $this->client->websocketApplications()->list($selectedCluster->id)->collect(),
            'Fetching WebSocket applications...',
        );

        if ($apps->isEmpty()) {
            $this->failAndExit('No WebSocket applications in that cluster. Create one with `cloud websocket-application:create`.');
        }

        $options = $currentId ? ['' => '— Detach —'] : [];

        foreach ($apps as $app) {
            /** @var WebsocketApplication $app */
            $options[$app->id] = $app->id === $currentId ? "{$app->name} (current)" : $app->name;
        }

        return (string) select(
            label: 'WebSocket application to attach',
            options: $options,
            default: $value ?? ($selectedCluster->id === $currentCluster?->id ? $currentId : null) ?? '',
        );
    }

    protected function selectBucketKeys(): string
    {
        $buckets = spin(
            fn () => $this->client->objectStorageBuckets()->list()->collect(),
            'Fetching buckets...',
        );

        if ($buckets->isEmpty()) {
            $this->failAndExit('No buckets found. Create one with `cloud bucket:create`.');
        }

        $entries = [];
        $hasDefault = false;

        do {
            $bucketId = (string) select(
                label: $entries === [] ? 'Bucket' : 'Another bucket',
                options: ($entries === [] ? ['' => '— Detach all —'] : []) + $buckets->mapWithKeys(
                    fn (ObjectStorageBucket $bucket) => [$bucket->id => $bucket->name],
                )->all(),
            );

            if ($bucketId === '') {
                return '';
            }

            $keys = spin(
                fn () => $this->client->bucketKeys()->list($bucketId)->collect(),
                'Fetching bucket keys...',
            );

            if ($keys->isEmpty()) {
                $this->failAndExit('That bucket has no keys. Create one with `cloud bucket-key:create`.');
            }

            $keyId = (string) select(
                label: 'Bucket key',
                options: $keys->mapWithKeys(fn (BucketKey $key) => [$key->id => "{$key->name} ({$key->permission})"])->all(),
            );

            // The API's own rule, which also keeps out the ':' and ',' the entries are joined with.
            $disk = text(
                label: 'Disk name',
                placeholder: 'public',
                required: true,
                validate: fn (string $name) => preg_match('/^[A-Za-z0-9 _-]{1,40}$/', $name)
                    ? null
                    : 'Use up to 40 letters, numbers, spaces, dashes, or underscores.',
            );

            $isDefault = ! $hasDefault && confirm(label: 'Make it the default disk?', default: false);
            $hasDefault = $hasDefault || $isDefault;

            $entries[] = $isDefault ? "{$keyId}:{$disk}:default" : "{$keyId}:{$disk}";
        } while (confirm(label: 'Attach another bucket?', default: false));

        return implode(',', $entries);
    }

    protected function collectDataAndUpdate(Environment $environment): Environment
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field, $key) => [
                $field->key => $field->label(),
            ])->toArray(),
            scroll: 15,
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateEnvironment($environment);
    }
}
