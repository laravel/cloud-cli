<?php

namespace App\Commands;

use App\Client\Requests\CreateDomainRequestData;
use App\Dto\Domain;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class DomainCreate extends BaseCommand
{
    protected ?string $jsonDataClass = Domain::class;

    protected $signature = 'domain:create
                            {environment? : The environment ID or name}
                            {--name= : The domain name}
                            {--www-redirect= : The redirect strategy}
                            {--wildcard-enabled= : Whether to enable wildcard}
                            {--verification-method= : The verification method}
                            {--cloudflare-strategy= : How the domain uses Cloudflare (none, dns, or dns_proxy)}
                            {--allow-downtime= : Whether to serve the domain without verifying it first}';

    protected $description = 'Create a new domain';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Domain');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $domain = $this->loopUntilValid(fn () => $this->createDomain($environment->id));

        $this->outputJsonIfWanted($domain);

        success("Domain created: {$domain->name}");

        if ($domain->actionRequired !== null) {
            warning("Action required: {$domain->actionRequiredLabel()}");
        }
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
                ))
                ->nonInteractively(fn () => false),
        );

        $this->form()->prompt(
            'verification_method',
            fn ($resolver) => $resolver
                ->fromInput(fn ($value) => select(
                    label: 'Verification method',
                    options: [
                        'pre_verification' => 'Pre-verification',
                        'real_time' => 'Real-time',
                    ],
                    default: $value ?? 'pre_verification',
                    required: true,
                    info: fn ($method) => match ($method) {
                        'pre_verification' => 'TXT before the domain is pointed to the environment.',
                        'real_time' => 'Requires domain to be pointed to the environment.',
                        default => '',
                    },
                ))
                ->nonInteractively(fn () => 'pre_verification'),
        );

        $this->form()->define(
            'cloudflare_strategy',
            fn ($resolver) => $resolver->fromInput(fn ($value) => select(
                label: 'Cloudflare strategy',
                options: [
                    'none' => 'None',
                    'dns' => 'Cloudflare DNS',
                    'dns_proxy' => 'Cloudflare DNS with proxy',
                ],
                default: $value ?? 'none',
                required: true,
                info: fn ($strategy) => match ($strategy) {
                    'none' => 'A records, no Cloudflare integration.',
                    'dns' => 'CNAME records on Cloudflare DNS only.',
                    'dns_proxy' => 'CNAME records with the Cloudflare proxy enabled.',
                    default => '',
                },
            )),
        );

        $this->form()->define(
            'allow_downtime',
            fn ($resolver) => $resolver->fromInput(fn ($value) => confirm(
                label: 'Allow downtime?',
                default: $value === null ? true : filter_var($value, FILTER_VALIDATE_BOOLEAN),
                hint: 'Choose no to verify ownership and issue the certificate before traffic moves.',
            )),
        );

        // Both are optional in the API, so only ask when someone is there to answer; otherwise omit them and let the API pick its defaults.
        if ($this->isInteractive()) {
            $this->form()->prompt('cloudflare_strategy');
            $this->form()->prompt('allow_downtime');
        }

        return spin(
            fn () => $this->client->domains()->create(
                new CreateDomainRequestData(
                    environmentId: $environmentId,
                    name: $this->form()->get('name'),
                    wwwRedirect: $this->form()->get('www_redirect'),
                    wildcardEnabled: $this->form()->boolean('wildcard_enabled'),
                    verificationMethod: $this->form()->get('verification_method'),
                    allowDowntime: $this->form()->boolean('allow_downtime'),
                    cloudflareStrategy: $this->form()->get('cloudflare_strategy'),
                ),
            ),
            'Creating domain...',
        );
    }
}
