<?php

namespace App\Commands;

use App\Client\Requests\CreateDomainRequestData;
use App\Dto\Domain;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class DomainCreate extends BaseCommand
{
    protected ?string $jsonDataClass = Domain::class;

    protected $signature = 'domain:create
                            {environment? : The environment ID or name}
                            {--name= : The domain name}
                            {--www-redirect= : The redirect strategy}
                            {--wildcard-enabled= : Whether to enable wildcard}
                            {--verification-method= : The verification method}';

    protected $description = 'Create a new domain';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Domain');

        $environment = $this->resolvers()->environment()->include('application')->from($this->argument('environment'));

        $domain = $this->loopUntilValid(fn () => $this->createDomain($environment->id));

        $this->outputJsonIfWanted($domain);

        success("Domain created: {$domain->name}");
    }

    protected function createDomain(string $environmentId)
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => text(
                label: 'Domain name',
                default: $value ?? '',
                required: true,
            )),
        );

        $this->form()->prompt(
            'www_redirect',
            fn ($resolver) => $resolver
                ->fromInput(fn ($value) => select(
                    label: 'Redirect strategy',
                    options: [
                        'www_to_root' => 'Redirect www to root',
                        'root_to_www' => 'Redirect non-www to www',
                    ],
                    required: true,
                    default: $value ?? 'www_to_root',
                ))
                ->nonInteractively(fn () => 'www_to_root'),
        );

        $this->form()->prompt(
            'wildcard_enabled',
            fn ($resolver) => $resolver
                ->fromInput(fn ($value) => confirm(
                    label: 'Enable wildcard',
                    default: $value ?? false,
                )),
        );

        $this->form()->prompt(
            'verification_method',
            fn ($resolver) => $resolver
                ->fromInput(fn ($value) => selectWithContext(
                    label: 'Verification method',
                    options: [
                        'pre_verification' => ['Pre-verification', 'TXT before the domain is pointed to the environment.'],
                        'real_time' => ['Real-time', 'Requires domain to be pointed to the environment.'],
                    ],
                    default: $value ?? 'pre_verification',
                    required: true,
                )),
        );

        return spin(
            fn () => $this->client->domains()->create(
                new CreateDomainRequestData(
                    environmentId: $environmentId,
                    name: $this->form()->get('name'),
                    wwwRedirect: $this->form()->get('www_redirect'),
                    wildcardEnabled: $this->form()->get('wildcard_enabled'),
                    verificationMethod: $this->form()->get('verification_method'),
                ),
            ),
            'Creating domain...',
        );
    }
}
