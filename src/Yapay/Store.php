<?php

declare(strict_types=1);

namespace YapaySdk;

final class Store
{
    public function __construct(
        private readonly string $tokenAccount,
        private readonly Environment $environment,
        private ?string $accessToken = null
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

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }
}
