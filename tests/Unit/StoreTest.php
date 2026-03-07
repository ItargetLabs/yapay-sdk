<?php

declare(strict_types=1);

namespace YapaySdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YapaySdk\Environment;
use YapaySdk\Store;

class StoreTest extends TestCase
{
    public function testGetters(): void
    {
        $env = Environment::sandbox();
        $store = new Store('token_abc', $env);

        $this->assertSame('token_abc', $store->getTokenAccount());
        $this->assertSame($env, $store->getEnvironment());
    }
}
