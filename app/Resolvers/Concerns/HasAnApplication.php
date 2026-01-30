<?php

namespace App\Resolvers\Concerns;

use App\Dto\Application;

trait HasAnApplication
{
    protected ?Application $application;

    public function withApplication(null|string|Application $application): self
    {
        if (is_string($application)) {
            $application = $this->resolvers()->application()->from($application);
        }

        $this->application = $application;

        return $this;
    }

    protected function application(): Application
    {
        return $this->application ??= $this->resolvers()->application()->resolve();
    }
}
