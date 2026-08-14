<?php

declare(strict_types=1);

namespace YapaySdk\CreditCard;

use Exception;
use GuzzleHttp\Client;
use YapaySdk\PaymentStatus;
use YapaySdk\Store;
use YapaySdk\YapayBaseClient;

final class CreditCardClient extends YapayBaseClient
{
    public function __construct(Store $store, ?Client $httpClient = null)
    {
        parent::__construct($store, $httpClient);
    }

    public function processPayment(CreditCardRequest $request): CreditCardResponse
    {
        return $this->processCreditCardPayment($request);
    }

    public function processCreditCardPayment(CreditCardRequest $request): CreditCardResponse
    {
        $payload = [
            'token_account' => $this->getTokenAccount(),
            'customer' => $this->buildCustomerPayload($request->customer),
            'transaction_product' => $this->buildTransactionProduct(
                amount: $request->amount,
                description: $request->description ?? 'Pagamento com cartao de credito',
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
                'payment_method_id' => '3',
                'card_name' => $request->creditCard->holderName,
                'card_number' => $request->creditCard->number,
                'card_expdate_month' => $request->creditCard->expirationMonth,
                'card_expdate_year' => $request->creditCard->expirationYear,
                'card_cvv' => $request->creditCard->securityCode,
                'split' => (string) ($request->installments ?? 1),
                'card_holder_doc' => preg_replace('/\D/', '', (string) ($request->customer->document ?? '')),
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

            $statusId = (int) ($transaction['status_id'] ?? 4);
            $status = self::mapYapayStatus($statusId);
            $transactionId = (string) ($transaction['transaction_id'] ?? $transaction['tid'] ?? '');
            $tokenTransaction = $this->extractTokenTransaction($transaction, $data);
            $amount = (float) ($transaction['price_payment'] ?? $request->amount);
            $nsu = $transaction['nsu'] ?? null;
            $authorizationCode = $transaction['authorization_code'] ?? null;
            $installments = (int) ($transaction['split'] ?? $request->installments ?? 1);
            $installmentAmount = $installments > 0 ? $amount / $installments : $amount;

            return new CreditCardResponse(
                tid: $transactionId,
                tokenTransaction: $tokenTransaction,
                status: $status,
                amount: $amount,
                currency: $request->currency,
                hash: $tokenTransaction,
                nsu: is_string($nsu) ? $nsu : null,
                installments: $installments > 1 ? $installments : null,
                installmentAmount: $installments > 1 ? $installmentAmount : null,
                authorizationCode: is_string($authorizationCode) ? $authorizationCode : null,
                gatewayResponse: $body + ['data_response' => $data]
            );
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function processInstallmentPayment(CreditCardRequest $request, int $installments): CreditCardResponse
    {
        return $this->processCreditCardPayment(new CreditCardRequest(
            amount: $request->amount,
            currency: $request->currency,
            customer: $request->customer,
            creditCard: $request->creditCard,
            installments: $installments,
            description: $request->description,
            number: $request->number,
            metadata: $request->metadata,
            affiliates: $request->affiliates
        ));
    }

    public function checkPaymentStatus(string $transactionId): PaymentStatus
    {
        try {
            return parent::checkPaymentStatus($transactionId);
        } catch (Exception) {
            return PaymentStatus::FAILED;
        }
    }

    public function getAcceptedCardBrands(): array
    {
        return [
            'visa',
            'mastercard',
            'amex',
            'elo',
            'hipercard',
            'diners',
        ];
    }

    public function getMaxInstallments(): int
    {
        return 12;
    }
}
