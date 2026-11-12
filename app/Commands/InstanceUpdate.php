<?php

namespace App\Commands;

use App\Client\Requests\UpdateInstanceRequestData;
use App\Dto\EnvironmentInstance;
use App\Dto\ValidationErrors;
use App\Exceptions\CommandExitException;
use InvalidArgumentException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class InstanceUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = EnvironmentInstance::class;

    protected $signature = 'instance:update
                            {instance? : The instance ID or name}
                            {--size= : Instance size}
                            {--min-replicas= : Minimum replicas}
                            {--max-replicas= : Maximum replicas}
                            {--scaling-type= : Scaling type}
                            {--uses-scheduler= : Uses scheduler}
                            {--scaling-cpu-threshold-percentage= : Scaling CPU threshold percentage}
                            {--scaling-memory-threshold-percentage= : Scaling memory threshold percentage}
                            {--uses-octane= : Uses Octane}
                            {--uses-inertia-ssr= : Uses Inertia SSR}
                            {--scale-to-zero= : Uses scale to zero}
                            {--scale-to-zero-timeout= : Minutes without requests before scaling to zero (enables scale to zero)}
                            {--sleep-with-app= : Scale worker replicas to zero alongside the app (worker instances only)}
                            {--startup-probe= : Startup check as comma-separated key=value pairs of path, port, delay, interval, timeout, tries (e.g. "path=/up,tries=60"), or none to remove it (app instances only)}
                            {--readiness-probe= : Readiness check, in the same format as --startup-probe}
                            {--liveness-probe= : Liveness check, in the same format as --startup-probe}
                            {--remove-probes : Remove all custom health checks}
                            {--hibernation= : Deprecated alias for --scale-to-zero}
                            {--hibernation-timeout= : Deprecated alias for --scale-to-zero-timeout}
                            {--force : Force update without confirmation}';

    protected $description = 'Update an instance';

    protected const PROBE_CHECKS = ['startup', 'readiness', 'liveness'];

    /**
     * Bounds from the API's UpdateInstanceRequest schema.
     *
     * @var array<string, array{int, int}>
     */
    protected const PROBE_LIMITS = [
        'port' => [1, 65535],
        'delay' => [0, 600],
        'interval' => [1, 300],
        'timeout' => [1, 120],
        'tries' => [1, 1000],
    ];

    protected const PROBE_PATH_MAX_LENGTH = 255;

    protected const STARTUP_PROBE_MAX_SECONDS = 600;

    /**
     * @var array<string, string>
     */
    protected array $deprecatedOptions = [
        'hibernation' => 'scale-to-zero',
        'hibernation-timeout' => 'scale-to-zero-timeout',
    ];

    public function options()
    {
        $options = parent::options();

        foreach ($this->deprecatedOptions as $deprecated => $current) {
            if (($options[$deprecated] ?? null) !== null && ($options[$current] ?? null) === null) {
                $options[$current] = $options[$deprecated];
            }
        }

        // Removing every check is the same as passing none for each, which keeps one path for the payload.
        if (($options['remove-probes'] ?? false) && ! $this->hasProbeOptions()) {
            foreach (self::PROBE_CHECKS as $check) {
                $options["{$check}-probe"] = 'none';
            }
        }

        return $options;
    }

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Instance');

        if ($this->option('remove-probes') && $this->hasProbeOptions()) {
            $this->outputErrorOrThrow('Use either --remove-probes or the --*-probe options, not both.');

            throw new CommandExitException(self::FAILURE);
        }

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $this->defineFields($instance);
        $this->validateProbeOptions();

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedInstance = $this->runUpdate(
            fn () => $this->updateInstance($instance),
            fn () => $this->collectDataAndUpdate($instance),
        );

        $this->outputJsonIfWanted($updatedInstance);

        success('Instance updated');
    }

    protected function updateInstance(EnvironmentInstance $instance): EnvironmentInstance
    {
        $hibernationTimeout = $this->resolveHibernationTimeout($instance);
        $probes = $this->resolveProbes($instance);

        spin(
            fn () => $this->client->instances()->update(
                new UpdateInstanceRequestData(
                    instanceId: $instance->id,
                    size: $this->form()->get('size'),
                    minReplicas: $this->form()->integer('min_replicas'),
                    maxReplicas: $this->form()->integer('max_replicas'),
                    scalingType: $this->form()->get('scaling_type'),
                    usesScheduler: $this->form()->boolean('uses_scheduler'),
                    scalingCpuThresholdPercentage: $this->form()->get('scaling_cpu_threshold_percentage'),
                    scalingMemoryThresholdPercentage: $this->form()->get('scaling_memory_threshold_percentage'),
                    usesOctane: $this->form()->boolean('uses_octane'),
                    usesInertiaSsr: $this->form()->boolean('uses_inertia_ssr'),
                    hibernationTimeout: $hibernationTimeout === false ? null : $hibernationTimeout,
                    disableHibernation: $hibernationTimeout === false,
                    sleepWithApp: $this->form()->boolean('sleep_with_app'),
                    probes: $probes === false ? null : $probes,
                    removeProbes: $probes === false,
                ),
            ),
            'Updating instance...',
        );

        return $this->client->instances()->get($instance->id);
    }

    protected function defineFields(EnvironmentInstance $instance): void
    {
        $this->form()->define(
            'size',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Size',
                    options: collect($this->client->instances()->sizes()->all())
                        ->mapWithKeys(fn ($size) => [$size->name => $size->description])
                        ->toArray(),
                    required: true,
                    default: $value ?? $instance->size,
                ),
            ),
        )->setPreviousValue($instance->size);

        $this->form()->define(
            'min_replicas',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => (int) number(
                    label: 'Minimum replicas',
                    default: (string) ($value ?? $instance->minReplicas),
                    min: 1,
                    max: 10,
                ),
            ),
            'min-replicas',
        )->setPreviousValue((string) $instance->minReplicas);

        $this->form()->define(
            'max_replicas',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => (int) number(
                    label: 'Maximum replicas',
                    default: (string) ($value ?? $instance->maxReplicas),
                    min: 1,
                    max: 10,
                ),
            ),
            'max-replicas',
        )->setPreviousValue((string) $instance->maxReplicas);

        $this->form()->define(
            'scaling_type',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Scaling type',
                    options: [
                        'none' => 'None',
                        'custom' => 'Custom',
                        'auto' => 'Auto',
                    ],
                    default: $value ?? $instance->scalingType,
                    required: true,
                ),
            ),
            'scaling-type',
        )->setPreviousValue($instance->scalingType);

        $this->form()->define(
            'uses_scheduler',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use scheduler?',
                    default: false,
                ),
            ),
            'uses-scheduler',
        )->setPreviousValue($instance->usesScheduler);

        $this->form()->define(
            'scaling_cpu_threshold_percentage',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Scaling CPU threshold percentage',
                    default: (string) ($value ?? $instance->scalingCpuThresholdPercentage),
                    min: 50,
                    max: 95,
                ),
            ),
            'scaling-cpu-threshold-percentage',
        )->setPreviousValue((string) $instance->scalingCpuThresholdPercentage)->setLabel('Scaling CPU threshold percentage');

        $this->form()->define(
            'scaling_memory_threshold_percentage',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Scaling memory threshold percentage',
                    default: (string) ($value ?? $instance->scalingMemoryThresholdPercentage),
                    min: 50,
                    max: 95,
                ),
            ),
            'scaling-memory-threshold-percentage',
        )->setPreviousValue((string) $instance->scalingMemoryThresholdPercentage);

        $this->form()->define(
            'uses_octane',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use Octane?',
                    default: $instance->usesOctane ?? false,
                ),
            ),
            'uses-octane',
        )->setPreviousValue($instance->usesOctane)->setLabel('Octane');

        $this->form()->define(
            'uses_inertia_ssr',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use Inertia SSR?',
                    default: $instance->usesInertiaSsr ?? false,
                ),
            ),
            'uses-inertia-ssr',
        )->setPreviousValue($instance->usesInertiaSsr)->setLabel('Inertia SSR');

        $this->form()->define(
            'scale_to_zero',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use scale to zero?',
                    default: $value ?? $instance->hibernationTimeout !== null,
                ),
            ),
            'scale-to-zero',
        )->setPreviousValue($instance->hibernationTimeout !== null)->setLabel('Scale to zero');

        $this->form()->define(
            'hibernation_timeout',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Scale to zero timeout',
                    default: (string) ($value ?? $instance->hibernationTimeout ?? ''),
                    required: true,
                    hint: 'Minutes without HTTP requests before the app scales to zero',
                ),
            ),
            'scale-to-zero-timeout',
        )->setPreviousValue($instance->hibernationTimeout)->setLabel('Scale to zero timeout');

        $this->form()->define(
            'sleep_with_app',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Scale worker replicas to zero alongside the app?',
                    default: $value ?? $instance->sleepWithApp ?? false,
                ),
            ),
            'sleep-with-app',
        )->setPreviousValue($instance->sleepWithApp)->setLabel('Sleep with app');

        $this->form()->define(
            'probes',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->promptForProbes($instance, $value),
            ),
        )->setLabel('Health checks');

        foreach (self::PROBE_CHECKS as $check) {
            $this->form()->define(
                "probes.{$check}",
                fn ($resolver) => $resolver->fromInput(
                    fn ($value) => $this->promptForProbe($instance, $check, $value),
                ),
                "{$check}-probe",
            )->setPreviousValue($this->formatProbe($this->currentProbe($instance, $check)))->setLabel(ucfirst($check).' check');
        }
    }

    /**
     * False means turn hibernation off, null means leave it unchanged.
     */
    protected function resolveHibernationTimeout(EnvironmentInstance $instance): int|false|null
    {
        $enabled = $this->form()->boolean('scale_to_zero');
        $timeout = $this->form()->integer('hibernation_timeout');

        if ($enabled === false) {
            return false;
        }

        if ($timeout !== null) {
            return $timeout;
        }

        if ($enabled !== true || $instance->hibernationTimeout !== null) {
            return null;
        }

        // Cloud turns hibernation on by receiving a timeout, so enabling it from off needs one.
        if (! $this->isInteractive()) {
            $this->outputErrorOrThrow('Provide --scale-to-zero-timeout to turn on scale to zero.');

            throw new CommandExitException(self::FAILURE);
        }

        $this->form()->prompt('hibernation_timeout');

        return $this->form()->integer('hibernation_timeout');
    }

    protected function hasProbeOptions(): bool
    {
        foreach (self::PROBE_CHECKS as $check) {
            if ($this->option("{$check}-probe") !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check the --*-probe values before anything is sent, asking again for a bad one when interactive.
     */
    protected function validateProbeOptions(): void
    {
        foreach (self::PROBE_CHECKS as $check) {
            while (($error = $this->probeError($check, $this->form()->get("probes.{$check}"))) !== null) {
                $message = "Invalid --{$check}-probe value: {$error}";

                if (! $this->isInteractive()) {
                    $this->outputErrorOrThrow($message);

                    throw new CommandExitException(self::FAILURE);
                }

                error($message);

                $errors = new ValidationErrors;
                $errors->add("probes.{$check}", $message);

                $this->form()->errors($errors)->prompt("probes.{$check}");
            }
        }
    }

    protected function probeError(string $check, ?string $spec): ?string
    {
        if ($spec === null) {
            return null;
        }

        try {
            $this->parseProbe($check, $spec);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * The API replaces every check at once, so the checks the user did not touch are sent back as they are.
     * False means remove all custom checks, null means leave them unchanged.
     *
     * @return array<string, array<string, mixed>>|false|null
     */
    protected function resolveProbes(EnvironmentInstance $instance): array|false|null
    {
        $changes = collect(self::PROBE_CHECKS)
            ->mapWithKeys(fn ($check) => [$check => $this->form()->get("probes.{$check}")])
            ->filter(fn ($spec) => $spec !== null);

        if ($changes->isEmpty()) {
            return null;
        }

        $probes = collect(self::PROBE_CHECKS)
            ->mapWithKeys(fn ($check) => [$check => $this->currentProbe($instance, $check)])
            ->filter(fn ($probe) => $probe !== null)
            ->all();

        foreach ($changes as $check => $spec) {
            $probe = $this->parseProbe($check, $spec);

            if ($probe === null) {
                unset($probes[$check]);
            } else {
                $probes[$check] = $probe;
            }
        }

        return $probes === [] ? false : $probes;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentProbe(EnvironmentInstance $instance, string $check): ?array
    {
        $probe = $instance->probes[$check] ?? null;

        return is_array($probe) ? $probe : null;
    }

    /**
     * Null means remove the check.
     *
     * @return array<string, int|string>|null
     *
     * @throws InvalidArgumentException
     */
    protected function parseProbe(string $check, string $spec): ?array
    {
        $spec = trim($spec);

        if ($spec === 'none') {
            return null;
        }

        if ($spec === '') {
            throw new InvalidArgumentException('provide comma-separated key=value pairs, or none to remove the check.');
        }

        $probe = [];

        foreach (explode(',', $spec) as $pair) {
            if (! str_contains($pair, '=')) {
                throw new InvalidArgumentException("\"{$pair}\" is not a key=value pair.");
            }

            [$field, $value] = array_map('trim', explode('=', $pair, 2));

            if ($field !== 'path' && ! array_key_exists($field, self::PROBE_LIMITS)) {
                throw new InvalidArgumentException("unknown key \"{$field}\". Use path, port, delay, interval, timeout, or tries.");
            }

            if (array_key_exists($field, $probe)) {
                throw new InvalidArgumentException("{$field} is set more than once.");
            }

            if ($value === '') {
                throw new InvalidArgumentException("{$field} needs a value.");
            }

            $error = $this->probeFieldError($field, $value);

            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }

            $probe[$field] = $field === 'path' ? $value : (int) $value;
        }

        $error = $this->probeBudgetError($check, $probe);

        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        return $probe;
    }

    protected function probeFieldError(string $field, string $value): ?string
    {
        if ($field === 'path') {
            return mb_strlen($value) > self::PROBE_PATH_MAX_LENGTH
                ? 'path may be at most '.self::PROBE_PATH_MAX_LENGTH.' characters.'
                : null;
        }

        [$min, $max] = self::PROBE_LIMITS[$field];

        if (! preg_match('/^\d+$/', $value)) {
            return "{$field} must be a whole number.";
        }

        if ((int) $value < $min || (int) $value > $max) {
            return "{$field} must be between {$min} and {$max}.";
        }

        return null;
    }

    /**
     * Cloud fills unset timings with platform defaults we don't know, so only check what the user gave.
     *
     * @param  array<string, int|string>  $probe
     */
    protected function probeBudgetError(string $check, array $probe): ?string
    {
        if ($check !== 'startup' || ! isset($probe['interval'], $probe['tries'])) {
            return null;
        }

        $seconds = (int) ($probe['delay'] ?? 0) + (int) $probe['interval'] * (int) $probe['tries'];

        if ($seconds <= self::STARTUP_PROBE_MAX_SECONDS) {
            return null;
        }

        return 'the startup check may take at most '.self::STARTUP_PROBE_MAX_SECONDS." seconds (delay + interval × tries), this one takes {$seconds}.";
    }

    /**
     * @param  array<string, mixed>|null  $probe
     */
    protected function formatProbe(?array $probe): ?string
    {
        if ($probe === null) {
            return null;
        }

        $pairs = collect(['path', ...array_keys(self::PROBE_LIMITS)])
            ->filter(fn ($field) => ($probe[$field] ?? null) !== null)
            ->map(fn ($field) => "{$field}={$probe[$field]}");

        return $pairs->isEmpty() ? 'defaults' : $pairs->implode(',');
    }

    /**
     * @param  array<int, string>|null  $value
     * @return array<int, string>
     */
    protected function promptForProbes(EnvironmentInstance $instance, ?array $value): array
    {
        $checks = multiselect(
            label: 'Which health checks do you want to edit?',
            options: collect(self::PROBE_CHECKS)->mapWithKeys(fn ($check) => [
                $check => ucfirst($check).' ('.($this->formatProbe($this->currentProbe($instance, $check)) ?? 'not set').')',
            ])->all(),
            default: $value ?? [],
            required: true,
            hint: 'Checks you leave out keep their current settings.',
        );

        foreach ($checks as $check) {
            $this->form()->prompt("probes.{$check}");
        }

        return $checks;
    }

    /**
     * Returns the check in the same key=value form the --*-probe options take, or none to remove it.
     */
    protected function promptForProbe(EnvironmentInstance $instance, string $check, ?string $value): string
    {
        $label = ucfirst($check).' check';
        $current = $this->currentProbe($instance, $check);

        try {
            $current = $value === null ? $current : $this->parseProbe($check, $value);
        } catch (InvalidArgumentException) {
            // A bad value from the command line still leaves the current check as the starting point.
        }

        if ($this->currentProbe($instance, $check) !== null) {
            $action = select(
                label: $label,
                options: ['edit' => 'Edit', 'remove' => 'Remove'],
                default: $value === 'none' ? 'remove' : 'edit',
            );

            if ($action === 'remove') {
                return 'none';
            }
        }

        while (true) {
            $probe = [];

            foreach (['path', ...array_keys(self::PROBE_LIMITS)] as $field) {
                $entered = trim(text(
                    label: "{$label}: {$field}",
                    default: (string) ($current[$field] ?? ''),
                    validate: fn ($input) => trim($input) === '' ? null : $this->probeFieldError($field, trim($input)),
                    hint: $this->probeFieldHint($field),
                ));

                if ($entered !== '') {
                    $probe[$field] = $field === 'path' ? $entered : (int) $entered;
                }
            }

            $error = $probe === []
                ? 'Set at least one field, or remove the check.'
                : $this->probeBudgetError($check, $probe);

            if ($error === null) {
                return (string) $this->formatProbe($probe);
            }

            error(ucfirst($error));

            $current = $probe;
        }
    }

    protected function probeFieldHint(string $field): string
    {
        if ($field === 'path') {
            return 'A path makes this an HTTP check, a port alone makes it TCP. Blank to unset.';
        }

        [$min, $max] = self::PROBE_LIMITS[$field];

        $unit = in_array($field, ['delay', 'interval', 'timeout'], true) ? ' seconds' : '';

        return "{$min}–{$max}{$unit}. Blank to unset.";
    }

    protected function collectDataAndUpdate(EnvironmentInstance $instance): EnvironmentInstance
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())
                // Each check is picked from the health checks prompt, not this menu.
                ->reject(fn ($field) => str_starts_with($field->key, 'probes.'))
                ->mapWithKeys(fn ($field) => [
                    $field->key => $field->label(),
                ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateInstance($instance);
    }
}
