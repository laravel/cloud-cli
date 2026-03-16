<?php

namespace App\Commands\Concerns;

use App\Client\Requests\UpdateApplicationAvatarRequestData;
use App\Dto\Application;
use App\Dto\Environment;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

trait MonitorsDeployedSite
{
    protected function tryToSetAvatar(Application $application): void
    {
        $avatars = $this->getAvatarCandidatesFromRepo();

        if ($avatars->isEmpty()) {
            return;
        }

        try {
            $path = $avatars->first();
            $this->client->applications()->updateAvatar(new UpdateApplicationAvatarRequestData(
                applicationId: $application->id,
                avatar: $this->getAvatarFromPath($path),
            ));
        } catch (Throwable $e) {
            // All good, this is a nice bonus but not critical
        }
    }

    protected function waitForUrlToBeReady(Environment $environment): bool
    {
        do {
            $response = Http::get($environment->url);
            Sleep::for(CarbonInterval::seconds(2));
        } while (! $response->successful() && ! $response->serverError());

        return $response->successful();
    }
}
