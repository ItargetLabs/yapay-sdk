<?php

declare(strict_types=1);

namespace YapaySdk\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use YapaySdk\Address;
use YapaySdk\Customer;
use YapaySdk\Environment;
use YapaySdk\Pix\PixRequest;
use YapaySdk\Store;
use YapaySdk\Yapay;

final class PixClientTest extends TestCase
{
    public function testCreatePixCharge(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data_response' => [
                    'transaction_id' => 'pix-1',
                    'status_id' => 4,
                    'qr_code' => 'base64-image',
                    'qr_code_text' => 'pix-code',
                    'pix_copy_paste' => 'pix-copy-paste',
                    'expires_in' => 60,
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $handlerStack = HandlerStack::create($mock);

        $sdk = new Yapay(
            new Store('token_account', Environment::sandbox()),
            new Client(['handler' => $handlerStack, 'base_uri' => 'https://example.test/'])
        );

        $response = $sdk->createPixCharge(new PixRequest(
            amount: 10.0,
            currency: 'BRL',
            customer: $this->customer(),
            description: 'PIX teste',
            number: '123'
        ));

        $this->assertSame('pix-1', $response->tid);
        $this->assertSame('pix-copy-paste', $response->pixCopyPaste);
        $this->assertSame(60, $response->expiresInMinutes);
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
