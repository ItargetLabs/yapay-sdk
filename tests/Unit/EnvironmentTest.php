<?php

declare(strict_types=1);

namespace YapaySdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YapaySdk\Environment;

class EnvironmentTest extends TestCase
{
    public function testSandboxUrl(): void
    {
        $env = Environment::sandbox();
        $this->assertSame('https://api.intermediador.sandbox.yapay.com.br/', $env->getApiUrl());
    }

    public function testProductionUrl(): void
    {
        $env = Environment::production();
        $this->assertSame('https://api.intermediador.yapay.com.br/', $env->getApiUrl());
    }
}
