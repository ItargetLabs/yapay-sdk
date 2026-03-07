<?php

declare(strict_types=1);

namespace YapaySdk\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use YapaySdk\Environment;
use YapaySdk\Store;
use YapaySdk\Yapay;

class GetByTokenTest extends TestCase
{
    public function testGetTransactionByToken(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                [],
                json_encode([
                    'message_response' => ['message' => 'success'],
                    'data_response' => [
                        'transaction' => [
                            'transaction_id' => 79690,
                            'status_name' => 'Aprovada',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            ),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack, 'base_uri' => 'https://example.test/']);

        $sdk = new Yapay(
            new Store('token_abc', Environment::sandbox()),
            $httpClient
        );

        $response = $sdk->getTransactionByToken('tok123');

        $this->assertSame('success', $response['message_response']['message']);
        $this->assertSame(79690, $response['data_response']['transaction']['transaction_id']);
    }
}
