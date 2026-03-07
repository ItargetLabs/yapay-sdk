<?php

declare(strict_types=1);

namespace YapaySdk;

final class Store
{
    public function __construct(
        private readonly string $tokenAccount,
        private readonly Environment $environment
    ) {
    }

    public function getTokenAccount(): string
    {
        return $this->tokenAccount;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }
}
