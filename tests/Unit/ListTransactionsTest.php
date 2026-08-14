<?php

declare(strict_types=1);

namespace YapaySdk\Tests\Unit;

use DomainException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use YapaySdk\Environment;
use YapaySdk\Store;
use YapaySdk\Yapay;

class ListTransactionsTest extends TestCase
{
    public function testListTransactions(): void
    {
        $history = [];
        $sdk = $this->sdkWithHistory($history, [
            new Response(
                200,
                [],
                json_encode([
                    'data' => [
                        [
                            'id' => 1727671,
                            'transaction_token' => 'tok_abc',
                            'order_number' => '923',
                            'status' => [
                                'id' => 6,
                                'name' => 'approved',
                            ],
                        ],
                    ],
                    'pagination' => [
                        'count' => 1,
                        'current_page' => 0,
                        'page_amount' => 1,
                        'per_page' => 20,
                    ],
                    'resource' => 'list',
                ], JSON_THROW_ON_ERROR)
            ),
        ]);

        $response = $sdk->listTransactions(
            [
                'id' => '1727671',
                'page' => 1,
                'per_page' => 20,
            ],
            'access_token_abc'
        );

        $this->assertSame(1727671, $response['data'][0]['id']);
        $this->assertSame('tok_abc', $response['data'][0]['transaction_token']);
        $this->assertSame('list', $response['resource']);

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v3/sales', $request->getUri()->getPath());
        $this->assertSame('access_token_abc', $query['access_token']);
        $this->assertSame('J', $query['type_response']);
        $this->assertSame('1727671', $query['id']);
        $this->assertSame('1', $query['page']);
        $this->assertSame('20', $query['per_page']);
    }

    public function testListTransactionsUsesStoreAccessToken(): void
    {
        $history = [];
        $sdk = $this->sdkWithHistory(
            $history,
            [new Response(200, [], json_encode(['data' => [], 'resource' => 'list'], JSON_THROW_ON_ERROR))],
            'stored_access_token'
        );

        $sdk->listTransactions();

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertSame('stored_access_token', $query['access_token']);
    }

    public function testListTransactionsRequiresAccessToken(): void
    {
        $sdk = $this->sdkWithHistory([], [new Response(200, [], '{}')]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('accessToken is required');

        $sdk->listTransactions();
    }

    public function testGenerateAccessTokenPersistsOnStore(): void
    {
        $store = new Store('token_account', Environment::sandbox());
        $history = [];
        $mock = new MockHandler([
            new Response(
                200,
                [],
                json_encode([
                    'message_response' => ['message' => 'success'],
                    'data_response' => [
                        'authorization' => [
                            'access_token' => 'new_access_token',
                            'refresh_token' => 'new_refresh_token',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            ),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        $sdk = new Yapay($store, new Client([
            'handler' => $handlerStack,
            'base_uri' => 'https://example.test/',
        ]));

        $response = $sdk->generateAccessToken('consumer_key', 'consumer_secret', 'auth_code');

        $this->assertSame('new_access_token', $response['data_response']['authorization']['access_token']);
        $this->assertSame('new_access_token', $store->getAccessToken());

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/authorizations/access_token', $request->getUri()->getPath());
    }

    public function testRefreshAccessToken(): void
    {
        $history = [];
        $sdk = $this->sdkWithHistory($history, [
            new Response(
                200,
                [],
                json_encode([
                    'message_response' => ['message' => 'success'],
                    'data_response' => [
                        'authorization' => [
                            'access_token' => 'refreshed_access_token',
                            'refresh_token' => 'refreshed_refresh_token',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            ),
        ], 'old_access_token');

        $response = $sdk->refreshAccessToken(refreshToken: 'refresh_token_abc');

        $this->assertSame(
            'refreshed_access_token',
            $response['data_response']['authorization']['access_token']
        );

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/authorizations/refresh', $request->getUri()->getPath());
    }

    /**
     * @param array<int, mixed> $history
     * @param Response[] $responses
     */
    private function sdkWithHistory(array &$history, array $responses, ?string $accessToken = null): Yapay
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        return new Yapay(
            new Store('token_account', Environment::sandbox(), $accessToken),
            new Client([
                'handler' => $handlerStack,
                'base_uri' => 'https://example.test/',
            ])
        );
    }
}
