<?php

declare(strict_types=1);

namespace YapaySdk\Bank;

use Exception;
use GuzzleHttp\Client;
use YapaySdk\Store;
use YapaySdk\YapayBaseClient;

final class BankClient extends YapayBaseClient
{
    public function __construct(Store $store, ?Client $httpClient = null)
    {
        parent::__construct($store, $httpClient);
    }

    public function generateBank(BankRequest $request): BankResponse
    {
        $payload = [
            'token_account' => $this->getTokenAccount(),
            'customer' => $this->buildCustomerPayload($request->customer),
            'transaction_product' => $this->buildTransactionProduct(
                amount: $request->amount,
                description: $request->description ?: 'Pagamento via boleto',
                number: $request->number,
                metadata: $request->metadata
            ),
            'transaction' => $this->buildTransactionPayload(
                metadata: $request->metadata,
                availablePaymentMethods: '2,3,4,5,6,7,14,15,16,18,19,21,22,23'
            ),
            'affiliates' => array_map(
                static fn($affiliate) => is_object($affiliate) && method_exists($affiliate, 'toArray')
                    ? $affiliate->toArray()
                    : (array) $affiliate,
                $request->affiliates
            ),
            'payment' => [
                'payment_method_id' => '6',
            ],
        ];

        // Limpa affiliates se vazio para não enviar array vazio
        if (empty($payload['affiliates'])) {
            unset($payload['affiliates']);
        }

        try {
            $body = $this->createTransaction($payload);
            $data = $this->transactionData($body);
            $transaction = $this->transactionDetails($body);
            $payment = $transaction['payment'] ?? [];

            $statusId = (int) ($transaction['status_id'] ?? $data['status_id'] ?? 4);
            $status = self::mapYapayStatus($statusId);
            $transactionId = (string) ($transaction['transaction_id'] ?? $data['transaction_id'] ?? '');
            $tokenTransaction = (string) ($transaction['token_transaction'] ?? $data['token_transaction'] ?? '');
            $digitableLine = (string) ($payment['linha_digitavel'] ?? $transaction['digitable_line'] ?? $data['digitable_line'] ?? '');
            $barCode = (string) ($payment['bar_code'] ?? $transaction['bar_code'] ?? $data['bar_code'] ?? '');
            $paymentUrl = (string) ($payment['url_payment'] ?? $transaction['url_payment'] ?? $data['url_payment'] ?? '');

            return new BankResponse(
                tid: $transactionId,
                status: $status,
                amount: $request->amount,
                currency: $request->currency,
                digitableLine: $digitableLine,
                barCode: $barCode,
                url: $paymentUrl,
                hash: $tokenTransaction,
                authorizationCode: null,
                gatewayResponse: $body
            );
        } catch (Exception $e) {
            throw new Exception('Erro ao gerar boleto na Yapay: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function getBankData(string $tokenTransaction): BankStatusResponse
    {
        try {
            $body = $this->getTransactionByToken($tokenTransaction);
            return $this->mapBankStatusResponse($body);
        } catch (Exception $e) {
            throw new Exception('Erro ao consultar boleto na Yapay via token: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    private function mapBankStatusResponse(array $body, ?string $fallbackId = null): BankStatusResponse
    {
        $data = $this->transactionData($body);
        $transaction = $this->transactionDetails($body);
        $payment = $transaction['payment'] ?? [];

        $statusId = (int) ($transaction['status_id'] ?? $data['status_id'] ?? 4);
        $status = self::mapYapayStatus($statusId);
        $amount = (float) ($payment['price_payment'] ?? $transaction['price_payment'] ?? $data['price_payment'] ?? 0);
        $transactionId = (string) ($transaction['transaction_id'] ?? $data['transaction_id'] ?? $fallbackId ?? '');
        $digitableLine = $payment['linha_digitavel'] ?? $transaction['digitable_line'] ?? $data['digitable_line'] ?? null;
        $barCode = $payment['bar_code'] ?? $transaction['bar_code'] ?? $data['bar_code'] ?? null;
        $paymentUrl = $payment['url_payment'] ?? $transaction['url_payment'] ?? $data['url_payment'] ?? null;

        return new BankStatusResponse(
            status: $status,
            transactionId: $transactionId,
            amount: $amount,
            feeAmount: 0.0,
            authorizationCode: null,
            nsu: (string) ($payment['tid'] ?? ''),
            tid: $transactionId,
            digitableLine: is_string($digitableLine) ? $digitableLine : null,
            barCode: is_string($barCode) ? $barCode : null,
            url: is_string($paymentUrl) ? $paymentUrl : null,
            bankNumber: (string) ($payment['payment_method_id'] ?? ''),
            rawResponse: $body
        );
    }

    public function getBankFile(string $bankId, array $searchParams = []): array
    {
        return [
            'bankId' => $bankId,
            'link' => (string) ($searchParams['externalUrl'] ?? ''),
        ];
    }
}
