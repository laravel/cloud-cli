<?php

namespace App\Commands;

use App\Client\Requests\PurgeEdgeCacheRequestData;
use App\Dto\Environment;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class EnvironmentPurgeEdgeCache extends BaseCommand
{
    /**
     * The label and example for each kind of purge target.
     */
    protected const TARGETS = [
        'path' => ['Path', '/blog/hello-world'],
        'prefix' => ['Path prefix', '/blog/'],
        'tag' => ['Cache tag', 'blog-posts'],
    ];

    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'environment:purge-edge-cache
                            {environment? : The environment ID or name}
                            {--path= : Purge a single path, e.g. /blog/hello-world}
                            {--prefix= : Purge every path under a prefix, e.g. /blog/}
                            {--tag= : Purge a Cache-Tag value (needs a dedicated edge network)}';

    protected $description = 'Purge the edge cache for an environment (everything, unless given a path, prefix, or tag)';

    protected $aliases = ['env:purge-edge-cache'];

    public function handle()
    {
        $this->ensureClient();

        intro('Purging Edge Cache');

        $given = array_values(array_filter(array_keys(self::TARGETS), fn (string $target) => filled($this->option($target))));

        if (count($given) > 1) {
            $this->failAndExit('Pass only one of --path, --prefix, or --tag.');
        }

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $target = $given[0] ?? $this->selectTarget();

        $updatedEnvironment = $this->loopUntilValid(fn () => $this->purge($environment, $target));

        $this->outputJsonIfWanted($updatedEnvironment);

        $scope = match ($target) {
            'path' => 'path '.$this->form()->get('path'),
            'prefix' => 'everything under '.$this->form()->get('prefix'),
            'tag' => 'tag '.$this->form()->get('tag'),
            default => 'everything',
        };

        // The API queues the purge rather than running it, so don't claim it's done.
        success("Edge cache purge queued for {$environment->name}: {$scope}.");
    }

    /**
     * Without a path, prefix, or tag the API purges everything, so that's the non-interactive default.
     */
    protected function selectTarget(): ?string
    {
        if (! $this->isInteractive()) {
            return null;
        }

        $target = select(
            label: 'What do you want to purge?',
            options: [
                'everything' => 'Everything',
                'path' => 'A single path',
                'prefix' => 'Everything under a path prefix',
                'tag' => 'A cache tag',
            ],
            default: 'everything',
        );

        return $target === 'everything' ? null : $target;
    }

    protected function purge(Environment $environment, ?string $target): Environment
    {
        if ($target !== null) {
            $this->form()->prompt(
                $target,
                fn ($resolver) => $resolver->fromInput(fn (?string $value) => text(
                    label: self::TARGETS[$target][0],
                    placeholder: self::TARGETS[$target][1],
                    default: $value ?? '',
                    required: true,
                    validate: fn (string $value) => $target !== 'tag' && ! str_starts_with($value, '/')
                        ? 'Start the path with a /.'
                        : null,
                    hint: $target === 'tag' ? 'Needs a dedicated edge network.' : '',
                )),
            );
        }

        return spin(
            fn () => $this->client->environments()->purgeEdgeCache(
                new PurgeEdgeCacheRequestData(
                    environmentId: $environment->id,
                    path: $target === 'path' ? $this->form()->get('path') : null,
                    prefix: $target === 'prefix' ? $this->form()->get('prefix') : null,
                    tag: $target === 'tag' ? $this->form()->get('tag') : null,
                ),
            ),
            'Purging edge cache...',
        );
    }
}
