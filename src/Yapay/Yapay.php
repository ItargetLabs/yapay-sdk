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

    public function getBankData(string $transactionId): BankStatusResponse
    {
        $client = new BankClient($this->store, $this->httpClient);
        return $client->getBankData($transactionId);
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

    public static function parseTransactionLookup(array $lookupResponse, array $fallback = []): array
    {
        return YapayBaseClient::parseTransactionLookup($lookupResponse, $fallback);
    }
}
