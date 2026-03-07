<?php

declare(strict_types=1);

namespace YapaySdk\Tests\Unit;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use YapaySdk\Address;
use YapaySdk\Bank\BankRequest;
use YapaySdk\BillAffiliate;
use YapaySdk\Customer;
use YapaySdk\Environment;
use YapaySdk\Store;
use YapaySdk\Yapay;

final class BankClientTest extends TestCase
{
    public function testGenerateBankWithSplitRules(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data_response' => [
                    'transaction_id' => 'tx-bank-1',
                    'status_id' => 4,
                    'digitable_line' => '111',
                    'bar_code' => '222',
                    'url_payment' => 'https://boleto.test/123',
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        $sdk = new Yapay(
            new Store('token_account', Environment::sandbox()),
            new Client(['handler' => $handlerStack, 'base_uri' => 'https://example.test/'])
        );

        $response = $sdk->generateBank(new BankRequest(
            amount: 100.0,
            currency: 'BRL',
            customer: $this->customer(),
            description: 'Boleto teste',
            dueDate: new DateTime('2026-03-10'),
            number: '123',
            metadata: [],
            affiliates: [new BillAffiliate(1, 50.0, 2)]
        ));

        $this->assertSame('tx-bank-1', $response->tid);
        $this->assertCount(1, $history);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('token_account', $payload['token_account']);
        $this->assertCount(1, $payload['transaction']['split_rules']);
        $this->assertSame(1, $payload['transaction']['split_rules'][0]['affiliate_id']);
        $this->assertEquals(50.0, $payload['transaction']['split_rules'][0]['amount']);
        $this->assertSame(2, $payload['transaction']['split_rules'][0]['amount_type']);
    }

    private function customer(): Customer
    {
        return new Customer(
            id: '1',
            name: 'Cliente',
            email: 'cliente@test.com',
            document: '12345678900',
            phone: '11999999999',
            address: new Address(
                street: 'Rua A',
                number: '100',
                zipCode: '01234567',
                neighborhood: 'Centro',
                city: 'Sao Paulo',
                state: 'SP'
            )
        );
    }
}
