<?php

declare(strict_types=1);

namespace YapaySdk;

use GuzzleHttp\Client;
use YapaySdk\Bank\BankClient;
use YapaySdk\Bank\BankRequest;
use YapaySdk\Bank\BankResponse;
use YapaySdk\Bank\BankStatusResponse;
use YapaySdk\CreditCard\CreditCardClient;
use YapaySdk\CreditCard\CreditCardRequest;
use YapaySdk\CreditCard\CreditCardResponse;
use YapaySdk\Pix\PixClient;
use YapaySdk\Pix\PixRequest;
use YapaySdk\Pix\PixResponse;
use YapaySdk\Pix\PixStatusResponse;

final class Yapay
{
    public function __construct(
        private readonly Store $store,
        private ?Client $httpClient = null
    ) {
    }

    public function getTransactionByToken(string $tokenTransaction): array
    {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->getTransactionByToken($tokenTransaction);
    }

    public function getTransactionById(string $transactionId): array
    {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->getTransactionById($transactionId);
    }

    /**
     * @param array<string, scalar|null> $filters
     */
    public function listTransactions(array $filters = [], ?string $accessToken = null): array
    {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->listTransactions($filters, $accessToken);
    }

    public function generateAccessToken(
        string $consumerKey,
        string $consumerSecret,
        string $code
    ): array {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->generateAccessToken($consumerKey, $consumerSecret, $code);
    }

    public function refreshAccessToken(?string $accessToken = null, ?string $refreshToken = null): array
    {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->refreshAccessToken($accessToken, $refreshToken);
    }

    public function createCreditCardPayment(CreditCardRequest $request): CreditCardResponse
    {
        $client = new CreditCardClient($this->store, $this->httpClient);
        return $client->processCreditCardPayment($request);
    }

    public function processInstallmentCreditCardPayment(
        CreditCardRequest $request,
        int $installments
    ): CreditCardResponse {
        $client = new CreditCardClient($this->store, $this->httpClient);
        return $client->processInstallmentPayment($request, $installments);
    }

    public function createPixCharge(PixRequest $request): PixResponse
    {
        $client = new PixClient($this->store, $this->httpClient);
        return $client->generatePixCharge($request);
    }

    public function generateBank(BankRequest $request): BankResponse
    {
        $client = new BankClient($this->store, $this->httpClient);
        return $client->generateBank($request);
    }

    public function checkPaymentStatus(string $transactionId): PaymentStatus
    {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->checkPaymentStatus($transactionId);
    }

    public function getBankData(string $tokenTransaction): BankStatusResponse
    {
        $client = new BankClient($this->store, $this->httpClient);
        return $client->getBankData($tokenTransaction);
    }

    public function checkPixStatus(string $transactionId): PixStatusResponse
    {
        $client = new PixClient($this->store, $this->httpClient);
        return $client->checkPixStatus($transactionId);
    }

    public function getPixPayload(string $transactionId): string
    {
        $client = new PixClient($this->store, $this->httpClient);
        return $client->getPixPayload($transactionId);
    }

    public static function parseSettlementWebhook(array $payload): array
    {
        return YapayBaseClient::parseSettlementWebhook($payload);
    }

    public function parseTransactionLookup(array $lookupResponse, array $fallback = []): array
    {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->parseTransactionLookup($lookupResponse, $fallback);
    }

    /**
     * @param array<string, mixed> $raw
     */
    public function lookupAmount(array $raw): float
    {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->lookupAmount($raw);
    }

    /**
     * @param array<string, mixed> $raw
     */
    public function lookupDigitableLine(array $raw): ?string
    {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->lookupDigitableLine($raw);
    }

    /**
     * @param array<string, mixed> $raw
     */
    public function lookupBarCode(array $raw): ?string
    {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->lookupBarCode($raw);
    }

    /**
     * @param array<string, mixed> $raw
     */
    public function lookupPixCopyPaste(array $raw): ?string
    {
        $client = new YapayBaseClient($this->store, $this->httpClient);
        return $client->lookupPixCopyPaste($raw);
    }
}
