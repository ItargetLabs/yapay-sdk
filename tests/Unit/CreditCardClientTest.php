<?php

declare(strict_types=1);

namespace YapaySdk\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use YapaySdk\Address;
use YapaySdk\CreditCard\CreditCard;
use YapaySdk\CreditCard\CreditCardRequest;
use YapaySdk\Customer;
use YapaySdk\Environment;
use YapaySdk\Store;
use YapaySdk\Yapay;

final class CreditCardClientTest extends TestCase
{
    public function testCreateCreditCardPayment(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data_response' => [
                    'transaction' => [
                        'transaction_id' => 'cc-1',
                        'token_transaction' => 'tok-cc-1',
                        'status_id' => 6,
                        'price_payment' => 150.0,
                        'split' => 3,
                        'nsu' => 'nsu-1',
                        'authorization_code' => 'auth-1',
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $handlerStack = HandlerStack::create($mock);

        $sdk = new Yapay(
            new Store('token_account', Environment::sandbox()),
            new Client(['handler' => $handlerStack, 'base_uri' => 'https://example.test/'])
        );

        $response = $sdk->createCreditCardPayment(new CreditCardRequest(
            amount: 150.0,
            currency: 'BRL',
            customer: $this->customer(),
            creditCard: new CreditCard(
                number: '4111111111111111',
                holderName: 'Cliente Teste',
                expirationMonth: '12',
                expirationYear: '2030',
                securityCode: '123'
            ),
            installments: 3,
            description: 'Cartao teste',
            number: '123'
        ));

        $this->assertSame('cc-1', $response->tid);
        $this->assertSame('tok-cc-1', $response->tokenTransaction);
        $this->assertSame('tok-cc-1', $response->hash);
        $this->assertSame(3, $response->installments);
        $this->assertSame(50.0, $response->installmentAmount);
        $this->assertSame('nsu-1', $response->nsu);
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
