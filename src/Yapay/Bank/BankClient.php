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
                availablePaymentMethods: '2,3,4,5,6,7,14,15,16,18,19,21,22,23',
                affiliates: $request->affiliates
            ),
            'payment' => [
                'payment_method_id' => '6',
            ],
        ];

        try {
            $body = $this->createTransaction($payload);
            $data = $this->transactionData($body);
            $transaction = $this->transactionDetails($body);

            $statusId = (int) ($data['status_id'] ?? $transaction['status_id'] ?? 4);
            $status = self::mapYapayStatus($statusId);
            $transactionId = (string) ($data['transaction_id'] ?? $transaction['transaction_id'] ?? '');
            $digitableLine = (string) ($data['digitable_line'] ?? $transaction['digitable_line'] ?? '');
            $barCode = (string) ($data['bar_code'] ?? $transaction['bar_code'] ?? '');
            $paymentUrl = (string) ($data['url_payment'] ?? $transaction['url_payment'] ?? '');

            $paramsUrl = [
                'number' => $request->number,
                'accountReceiveIds' => $request->metadata['accountReceiveIds'] ?? [],
                'gatewayId' => $request->metadata['gatewayId'] ?? null,
                'externalUrl' => $paymentUrl,
            ];
            $token = base64_encode(json_encode($paramsUrl, JSON_THROW_ON_ERROR));
            $baseUrl = rtrim($this->getBaseUrl(), '/');

            return new BankResponse(
                tid: $transactionId,
                status: $status,
                amount: $request->amount,
                currency: $request->currency,
                digitableLine: $digitableLine,
                barCode: $barCode,
                url: $baseUrl !== ''
                    ? $baseUrl . '/api/payments/bank/print?' . http_build_query(['token' => $token])
                    : $paymentUrl,
                hash: $transactionId,
                authorizationCode: null,
                gatewayResponse: $body
            );
        } catch (Exception $e) {
            throw new Exception('Erro ao gerar boleto na Yapay: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function isBankPaid(string $bankId): bool
    {
        try {
            return $this->getBankData($bankId)->isCompleted();
        } catch (Exception) {
            return false;
        }
    }

    public function getBankData(string $bankId): BankStatusResponse
    {
        try {
            $body = $this->getTransactionById($bankId);
            $data = $this->transactionData($body);
            $transaction = $this->transactionDetails($body);

            $statusId = (int) ($data['status_id'] ?? $transaction['status_id'] ?? 4);
            $status = self::mapYapayStatus($statusId);
            $amount = (float) ($data['price_payment'] ?? $transaction['price_payment'] ?? 0);
            $digitableLine = $data['digitable_line'] ?? $transaction['digitable_line'] ?? null;
            $barCode = $data['bar_code'] ?? $transaction['bar_code'] ?? null;

            return new BankStatusResponse(
                status: $status,
                transactionId: $bankId,
                amount: $amount,
                feeAmount: 0.0,
                authorizationCode: null,
                nsu: null,
                tid: $bankId,
                digitableLine: is_string($digitableLine) ? $digitableLine : null,
                barCode: is_string($barCode) ? $barCode : null,
                bankNumber: null,
                rawResponse: $body
            );
        } catch (Exception $e) {
            throw new Exception('Erro ao consultar boleto na Yapay: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function getBankFile(string $bankId, array $searchParams = []): array
    {
        return [
            'bankId' => $bankId,
            'link' => (string) ($searchParams['externalUrl'] ?? ''),
        ];
    }
}
