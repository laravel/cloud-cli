<?php

namespace App\Commands;

use App\Client\Requests\CreateDomainRequestData;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class DomainCreate extends BaseCommand
{
    protected $signature = 'domain:create
                            {environment? : The environment ID or name}
                            {--name= : The domain name}
                            {--www-redirect= : The redirect strategy}
                            {--wildcard-enabled= : Whether to enable wildcard}
                            {--verification-method= : The verification method}
                            {--json : Output as JSON}';

    protected $description = 'Create a new domain';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Domain');

        $environment = $this->resolvers()->environment()->include('application')->from($this->argument('environment'));
        $domain = $this->loopUntilValid(fn () => $this->createDomain($environment->id));

        $this->outputJsonIfWanted($domain);

        outro("Domain created: {$domain->name}");
    }

    protected function createDomain(string $environmentId)
    {
        $this->fields()->add(
            'name',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => text(
                label: 'Domain name',
                default: $value ?? '',
                required: true,
            )),
        );

        $this->fields()->add(
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

        $this->fields()->add(
            'wildcard_enabled',
            fn ($resolver) => $resolver
                ->fromInput(fn ($value) => confirm(
                    label: 'Enable wildcard',
                    default: $value ?? false,
                )),
        );

        $this->fields()->add(
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
            fn () => $this->client->domains()->create(new CreateDomainRequestData(
                environmentId: $environmentId,
                name: $this->fields()->get('name'),
                wwwRedirect: $this->fields()->get('www_redirect'),
                wildcardEnabled: $this->fields()->get('wildcard_enabled'),
                verificationMethod: $this->fields()->get('verification_method'),
            )),
            'Creating domain...',
        );
    }
}
