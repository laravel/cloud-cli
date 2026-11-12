<?php

namespace App\Concerns;

use App\Dto\Database;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseUser;
use App\Enums\DatabaseUserType;
use App\Support\Form;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

trait DefinesDatabaseUserAccess
{
    protected function form(): Form
    {
        if (! isset($this->form)) {
            parent::form()->options([...$this->options(), 'database' => $this->databaseOption()]);
        }

        return $this->form;
    }

    /**
     * An array option that was never passed arrives as [], which Form would take as an answer,
     * so only --no-databases may send an empty list.
     *
     * @return list<string>|null
     */
    protected function databaseOption(): ?array
    {
        $databases = $this->option('database');

        if ($this->hasOption('no-databases') && $this->option('no-databases')) {
            if ($databases !== []) {
                $this->failAndExit('Pass either --database or --no-databases, not both.');
            }

            return [];
        }

        return $databases === [] ? null : $databases;
    }

    protected function defineTypeField(?DatabaseUser $user = null): void
    {
        $this->form()->define(
            'type',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => select(
                    label: 'Permission',
                    options: DatabaseUserType::assignableOptions(),
                    default: $value ?? $user?->type->value ?? DatabaseUserType::READ_WRITE->value,
                    required: true,
                ),
            )->nonInteractively(fn () => DatabaseUserType::READ_WRITE->value),
        )->setLabel('Permission')->setPreviousValue($user?->type->value);
    }

    protected function defineDatabasesField(DatabaseCluster $cluster, ?DatabaseUser $user = null): void
    {
        $this->form()->define(
            'databases',
            fn ($resolver) => $resolver->fromInput(
                fn (?array $value) => empty($cluster->schemas) ? [] : multiselect(
                    label: 'Databases',
                    options: collect($cluster->schemas)->mapWithKeys(fn (Database $d) => [$d->id => $d->name])->toArray(),
                    default: $value !== null
                        ? $this->resolveDatabaseIds($cluster, $value, strict: false)
                        : collect($user->databases ?? [])->pluck('id')->all(),
                    hint: 'The user can only reach the databases you select.',
                ),
            )->nonInteractively(fn () => []),
            'database',
        )->setLabel('Databases')->setPreviousValue($user ? $this->databaseNames($user->databases) : null);
    }

    /**
     * @param  list<string>  $identifiers  Database IDs or names
     * @return list<string>
     */
    protected function resolveDatabaseIds(DatabaseCluster $cluster, array $identifiers, bool $strict = true): array
    {
        $schemas = collect($cluster->schemas);

        return collect($identifiers)
            ->map(function (string $identifier) use ($schemas, $cluster, $strict) {
                $schema = $schemas->firstWhere('id', $identifier) ?? $schemas->firstWhere('name', $identifier);

                if (! $schema && $strict) {
                    $this->failAndExit("Unable to resolve database: {$identifier}. Run `cloud database:list {$cluster->id} --json` to see available databases.");
                }

                return $schema?->id;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $ids
     * @return list<Database>
     */
    protected function databasesFromIds(DatabaseCluster $cluster, array $ids): array
    {
        return collect($cluster->schemas)->whereIn('id', $ids)->values()->all();
    }

    /**
     * @param  array<Database>  $databases
     */
    protected function databaseNames(array $databases): string
    {
        return collect($databases)->pluck('name')->join(', ') ?: 'None';
    }
}
