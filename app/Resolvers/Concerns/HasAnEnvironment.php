<?php

namespace App\Resolvers\Concerns;

use App\Dto\Environment;

trait HasAnEnvironment
{
    protected ?Environment $environment;

    public function withEnvironment(null|string|Environment $environment): self
    {
        if (is_string($environment)) {
            $environment = $this->resolvers()->environment()->from($environment);
        }

        $this->environment = $environment;

        return $this;
    }

    protected function environment(): Environment
    {
        return $this->environment ??= $this->resolvers()->environment()->resolve();
    }
}
