<?php

declare(strict_types=1);

namespace YapaySdk;

use DateTime;
use DomainException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class YapayBaseClient
{
    private Client $httpClient;

    public function __construct(private readonly Store $store, ?Client $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->store->getEnvironment()->getApiUrl(),
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    public function getTransactionByToken(string $tokenTransaction): array
    {
        if ($tokenTransaction === '') {
            throw new DomainException('tokenTransaction is required');
        }

        return $this->request(
            'GET',
            'api/v3/transactions/get_by_token',
            [
                'query' => [
                    'token_account' => $this->store->getTokenAccount(),
                    'token_transaction' => $tokenTransaction,
                ],
            ]
        );
    }

    public function getTransactionById(string $transactionId): array
    {
        if ($transactionId === '') {
            throw new DomainException('transactionId is required');
        }

        return $this->request(
            'GET',
            'api/v3/transactions/payment/' . urlencode($transactionId),
            [
                'query' => [
                    'token_account' => $this->store->getTokenAccount(),
                ],
            ]
        );
    }

    public function getTokenAccount(): string
    {
        return $this->store->getTokenAccount();
    }

    public static function parseSettlementWebhook(array $payload): array
    {
        $transaction = $payload['transaction'] ?? [];
        $payment = $transaction['payment'] ?? [];
        $statusRaw = $transaction['status_id'] ?? $transaction['status_name'] ?? null;
        $paymentMethodRaw = $payment['payment_method_id'] ?? $transaction['payment_method_id'] ?? null;
        $dateTransaction = $transaction['date_transaction'] ?? null;
        $dateLow = $payment['date_approval'] ?? $payment['date_payment'] ?? $transaction['date_payment'] ?? null;

        return [
            'tid' => (string) ($transaction['transaction_id'] ?? ''),
            'transactionId' => (string) ($transaction['order_number'] ?? ''),
            'tokenTransaction' => (string) ($payload['token_transaction'] ?? $transaction['token_transaction'] ?? ''),
            'paymentMethodCode' => self::normalizePaymentMethod((string) $paymentMethodRaw),
            'statusCode' => self::normalizeStatus((string) $statusRaw),
            'lowDate' => self::parseDate((string) $dateLow),
            'occurrenceDate' => self::parseDate((string) $dateTransaction),
            'rawPayload' => $payload,
        ];
    }

    public static function parseTransactionLookup(array $lookupResponse, array $fallback = []): array
    {
        $dataResponse = is_array($lookupResponse['data_response'] ?? null)
            ? $lookupResponse['data_response']
            : [];
        $transaction = is_array($dataResponse['transaction'] ?? null)
            ? $dataResponse['transaction']
            : [];
        $payment = is_array($transaction['payment'] ?? null)
            ? $transaction['payment']
            : [];

        $statusRaw = (string) ($transaction['status_name'] ?? $transaction['status_id'] ?? '');
        $paymentMethodRaw = (string) ($payment['payment_method_id'] ?? $payment['payment_method_name'] ?? '');
        $dateTransaction = (string) ($transaction['date_transaction'] ?? '');
        $dateLow = (string) ($payment['date_approval'] ?? $payment['date_payment'] ?? '');

        return [
            ...$fallback,
            'tid' => (string) ($transaction['transaction_id'] ?? ($fallback['tid'] ?? '')),
            'transactionId' => (string) ($transaction['order_number'] ?? ($fallback['transactionId'] ?? '')),
            'paymentMethodCode' => self::normalizePaymentMethod($paymentMethodRaw),
            'statusCode' => self::normalizeStatus($statusRaw),
            'lowDate' => self::parseDate($dateLow),
            'occurrenceDate' => self::parseDate($dateTransaction),
            'apiResponse' => $lookupResponse,
        ];
    }

    public static function mapYapayStatus(int $statusId): PaymentStatus
    {
        return match ($statusId) {
            4 => PaymentStatus::WAITING_PAYMENT,
            6 => PaymentStatus::APPROVED,
            7 => PaymentStatus::CANCELLED,
            24 => PaymentStatus::CONTESTATION,
            87 => PaymentStatus::MONITORING,
            89 => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }

    protected function request(string $method, string $path, array $options): array
    {
        try {
            $response = $this->httpClient->request($method, $path, $options);
            $decoded = json_decode((string) $response->getBody(), true);
            return is_array($decoded) ? $decoded : [];
        } catch (GuzzleException $e) {
            $message = $e->getMessage();
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $body = (string) $e->getResponse()->getBody();
                $message .= " | Response Body: " . $body;
            }
            throw new Exception($message, (int) $e->getCode(), $e);
        }
    }

    protected function createTransaction(array $payload): array
    {
        return $this->request('POST', 'api/v3/transactions/payment', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);
    }

    protected function transactionData(array $response): array
    {
        $data = $response['data_response'] ?? $response;
        return is_array($data) ? $data : [];
    }

    protected function transactionDetails(array $response): array
    {
        $data = $this->transactionData($response);
        $transaction = $data['transaction'] ?? $data;
        return is_array($transaction) ? $transaction : [];
    }

    public function checkPaymentStatus(string $transactionId): PaymentStatus
    {
        $response = $this->getTransactionById($transactionId);
        $transaction = $this->transactionDetails($response);
        $statusId = (int) ($transaction['status_id'] ?? 4);
        return self::mapYapayStatus($statusId);
    }

    protected function buildCustomerPayload(Customer $customer): array
    {
        $address = $this->buildAddress($customer);
        $contactPhone = preg_replace('/\D/', '', (string) ($customer->phone ?? ''));

        $payload = [
            'addresses' => $address ? [$address] : [],
            'name' => $customer->name,
            'birth_date' => null,
            'cpf' => preg_replace('/\D/', '', (string) ($customer->document ?? '')),
            'email' => $customer->email,
        ];

        // Se houver um telefone válido (mínimo 10 dígitos), adiciona ao payload
        if (strlen($contactPhone) >= 10) {
            $payload['phone'] = $contactPhone;
            $payload['contacts'] = [
                [
                    'type_contact' => 'M',
                    'number_contact' => $contactPhone,
                ]
            ];
        }

        return $payload;
    }

    protected function buildTransactionProduct(
        float $amount,
        string $description,
        ?string $number,
        array $metadata
    ): array {
        return [[
            'description' => $description,
            'quantity' => '1',
            'price_unit' => number_format($amount, 2, '.', ''),
            'code' => (string) ($number ?? ''),
            'sku_code' => (string) ($number ?? ''),
            'extra' => $metadata['extra'] ?? '',
        ]];
    }

    protected function buildTransactionPayload(
        array $metadata,
        string $availablePaymentMethods
    ): array {
        return [
            'available_payment_methods' => $availablePaymentMethods,
            'customer_ip' => $metadata['customer_ip'] ?? '127.0.0.1',
            'shipping_type' => $metadata['shipping_type'] ?? '',
            'shipping_price' => $metadata['shipping_price'] ?? '',
            'price_discount' => '',
            'url_notification' => $metadata['url_notification'] ?? '',
            'free' => $metadata['free'] ?? '',
        ];
    }

    protected function buildAddress(Customer $customer): ?array
    {
        if (!$customer->address) {
            return null;
        }

        return [
            'type_address' => 'B',
            'postal_code' => preg_replace('/\D/', '', $customer->address->zipCode),
            'street' => $customer->address->street,
            'number' => $customer->address->number,
            'completion' => $customer->address->complement ?? '',
            'neighborhood' => $customer->address->neighborhood,
            'city' => $customer->address->city,
            'state' => $customer->address->state,
        ];
    }

    protected function getBaseUrl(): string
    {
        try {
            if (\function_exists('url') && \function_exists('app')) {
                $app = app();
                if (!\is_object($app)) {
                    return '';
                }

                if (method_exists($app, 'bound') && $app->bound('url')) {
                    return (string) url('/');
                }
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    private static function normalizePaymentMethod(string $method): string
    {
        $normalized = strtolower(trim($method));

        if ($normalized === '27' || str_contains($normalized, 'pix')) {
            return 'pix';
        }

        if ($normalized === '6' || str_contains($normalized, 'boleto')) {
            return 'bank_slip';
        }

        return 'bank_slip';
    }

    private static function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            '6', 'approved', 'aprovada', 'paid', 'success' => 'paid',
            '7', '89', 'cancelled', 'canceled', 'cancelada', 'reprovada', 'failed', 'refused' => 'cancelled',
            default => 'pending',
        };
    }

    private static function parseDate(string $value): DateTime
    {
        if ($value === '') {
            return new DateTime();
        }

        try {
            return new DateTime($value);
        } catch (Exception) {
            return new DateTime();
        }
    }
}
