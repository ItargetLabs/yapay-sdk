<?php

declare(strict_types=1);

namespace YapaySdk;

final class Environment
{
    private function __construct(private readonly string $apiUrl)
    {
    }

    public static function production(): self
    {
        return new self('https://api.intermediador.yapay.com.br/');
    }

    public static function sandbox(): self
    {
        return new self('https://api.intermediador.sandbox.yapay.com.br/');
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }
}
