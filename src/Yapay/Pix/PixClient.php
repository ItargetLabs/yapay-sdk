<?php

declare(strict_types=1);

namespace YapaySdk\Pix;

use Exception;
use GuzzleHttp\Client;
use YapaySdk\PaymentStatus;
use YapaySdk\Store;
use YapaySdk\YapayBaseClient;

final class PixClient extends YapayBaseClient
{
    public function __construct(Store $store, ?Client $httpClient = null)
    {
        parent::__construct($store, $httpClient);
    }

    public function generatePixCharge(PixRequest $request): PixResponse
    {
        $payload = [
            'token_account' => $this->getTokenAccount(),
            'customer' => $this->buildCustomerPayload($request->customer),
            'transaction_product' => $this->buildTransactionProduct(
                amount: $request->amount,
                description: $request->description ?? 'Pagamento via PIX',
                number: $request->number,
                metadata: $request->metadata
            ),
            'transaction' => $this->buildTransactionPayload(
                metadata: $request->metadata,
                availablePaymentMethods: '2,3,4,5,6,7,14,15,16,18,19,21,22,23,27'
            ),
            'affiliates' => array_map(
                static fn($affiliate) => is_object($affiliate) && method_exists($affiliate, 'toArray')
                    ? $affiliate->toArray()
                    : (array) $affiliate,
                $request->affiliates
            ),
            'payment' => [
                'payment_method_id' => '27',
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

            $statusId = (int) ($data['status_id'] ?? $transaction['status_id'] ?? 4);
            $status = self::mapYapayStatus($statusId);
            $transactionId = (string) ($data['transaction_id'] ?? $transaction['transaction_id'] ?? '');
            
            $qrCodeImage = (string) (
                $data['qr_code'] 
                ?? $transaction['qr_code'] 
                ?? $payment['qrcode_path'] 
                ?? ''
            );
            
            $qrCodeText = (string) (
                $data['qr_code_text'] 
                ?? $transaction['qr_code_text'] 
                ?? $payment['qrcode_original_path'] 
                ?? ''
            );
            
            $pixCopyPaste = (string) (
                $data['pix_copy_paste'] 
                ?? $transaction['pix_copy_paste'] 
                ?? $payment['qrcode_original_path'] 
                ?? $qrCodeText
            );

            $expiresAt = $transaction['max_days_to_keep_waiting_payment'] ?? null;
            $expiresInMinutes = 0;
            if ($expiresAt) {
                $expiresAtDate = new \DateTime($expiresAt);
                $now = new \DateTime();
                $diff = $now->diff($expiresAtDate);
                $expiresInMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
            }

            if ($expiresInMinutes === 0) {
                $expiresInMinutes = (int) ($data['expires_in'] ?? $transaction['expires_in'] ?? 0);
            }

            return new PixResponse(
                tid: $transactionId,
                status: $status,
                amount: $request->amount,
                currency: $request->currency,
                pixId: $transactionId,
                qrCode: $qrCodeImage,
                qrCodeText: $qrCodeText,
                pixCopyPaste: $pixCopyPaste,
                expiresInMinutes: $expiresInMinutes,
                authorizationCode: null,
                gatewayResponse: $body
            );
        } catch (Exception $e) {
            throw new Exception('Erro ao gerar cobranca PIX na Yapay: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function generatePixQRCode(string $pixCode): string
    {
        return $pixCode;
    }

    public function checkPixStatus(string $pixId): PixStatusResponse
    {
        try {
            $body = $this->getTransactionById($pixId);
            $data = $this->transactionData($body);
            $transaction = $this->transactionDetails($body);
            $payment = $transaction['payment'] ?? [];

            $statusId = (int) ($data['status_id'] ?? $transaction['status_id'] ?? 4);
            $status = self::mapYapayStatus($statusId);
            $amount = (float) ($data['price_payment'] ?? $transaction['price_payment'] ?? $payment['price_payment'] ?? 0);
            $pixCopyPaste = (string) (
                $data['pix_copy_paste'] 
                ?? $transaction['pix_copy_paste'] 
                ?? $payment['qrcode_original_path'] 
                ?? ''
            );

            return new PixStatusResponse(
                status: $status,
                tid: $pixId,
                nsu: null,
                amount: $amount,
                authorizationCode: null,
                payerSolicitation: null,
                location: null,
                occurrenceDate: null,
                lowDate: null,
                pixCopyPaste: $pixCopyPaste,
                rawResponse: $body
            );
        } catch (Exception $e) {
            throw new Exception('Erro ao consultar PIX na Yapay: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function getPixPayload(string $pixId): string
    {
        return $this->checkPixStatus($pixId)->pixCopyPaste ?? '';
    }

    public function checkPaymentStatus(string $transactionId): PaymentStatus
    {
        try {
            return $this->checkPixStatus($transactionId)->status;
        } catch (Exception) {
            return PaymentStatus::FAILED;
        }
    }
}
