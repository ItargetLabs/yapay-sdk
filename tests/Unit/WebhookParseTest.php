<?php

declare(strict_types=1);

namespace YapaySdk\Tests\Unit;

use DateTime;
use PHPUnit\Framework\TestCase;
use YapaySdk\Environment;
use YapaySdk\Store;
use YapaySdk\Yapay;

class WebhookParseTest extends TestCase
{
    public function testParseSettlementWebhook(): void
    {
        $payload = [
            'token_transaction' => 'cb22c716c80ddbaa16f8b8dbc49302a2',
            'transaction' => [
                'transaction_id' => 79690,
                'order_number' => '79690',
                'status_name' => 'Aprovada',
                'date_transaction' => '2026-03-06 09:00:00',
                'payment' => [
                    'payment_method_id' => 6,
                    'date_approval' => '2026-03-06 10:00:00',
                ],
            ],
        ];

        $parsed = Yapay::parseSettlementWebhook($payload);

        $this->assertSame('79690', $parsed['tid']);
        $this->assertSame('79690', $parsed['transactionId']);
        $this->assertSame('cb22c716c80ddbaa16f8b8dbc49302a2', $parsed['tokenTransaction']);
        $this->assertSame('bank_slip', $parsed['paymentMethodCode']);
        $this->assertSame('paid', $parsed['statusCode']);
        $this->assertInstanceOf(DateTime::class, $parsed['lowDate']);
        $this->assertInstanceOf(DateTime::class, $parsed['occurrenceDate']);
    }

    public function testParseTransactionLookupExtractsBankFields(): void
    {
        $sdk = $this->sdk();
        $parsed = $sdk->parseTransactionLookup($this->bankLookupPayload());

        $this->assertSame('79690', $parsed['tid']);
        $this->assertSame(150.5, $parsed['amount']);
        $this->assertSame(
            '23793.38128 60000.000003 00000.000400 1 84490000015050',
            $parsed['digitableLine']
        );
        $this->assertSame('23791844900000150503381260000000000000000004', $parsed['barCode']);
    }

    public function testLookupHelpersReadNestedPaymentFields(): void
    {
        $sdk = $this->sdk();
        $raw = $this->bankLookupPayload();

        $this->assertSame(150.5, $sdk->lookupAmount($raw));
        $this->assertSame(
            '23793.38128 60000.000003 00000.000400 1 84490000015050',
            $sdk->lookupDigitableLine($raw)
        );
        $this->assertSame('23791844900000150503381260000000000000000004', $sdk->lookupBarCode($raw));
    }

    public function testLookupHelpersReadFieldsAtDataResponseRoot(): void
    {
        $sdk = $this->sdk();
        $raw = [
            'data_response' => [
                'price_payment' => '99.90',
                'digitable_line' => '111',
                'bar_code' => '222',
            ],
        ];

        $this->assertSame(99.9, $sdk->lookupAmount($raw));
        $this->assertSame('111', $sdk->lookupDigitableLine($raw));
        $this->assertSame('222', $sdk->lookupBarCode($raw));
    }

    public function testParseTransactionLookupExtractsPixFields(): void
    {
        $sdk = $this->sdk();
        $parsed = $sdk->parseTransactionLookup($this->pixLookupPayload());

        $this->assertSame('79691', $parsed['tid']);
        $this->assertSame(80.0, $parsed['amount']);
        $this->assertSame('00020126580014br.gov.bcb.pix', $parsed['pixCopyPaste']);
    }

    public function testLookupPixCopyPasteReadsNestedAndRootFields(): void
    {
        $sdk = $this->sdk();

        $this->assertSame(
            '00020126580014br.gov.bcb.pix',
            $sdk->lookupPixCopyPaste($this->pixLookupPayload())
        );
        $this->assertSame(
            'pix-root',
            $sdk->lookupPixCopyPaste([
                'data_response' => [
                    'pix_copy_paste' => 'pix-root',
                ],
            ])
        );
        $this->assertSame(
            'pix-qrcode',
            $sdk->lookupPixCopyPaste([
                'data_response' => [
                    'transaction' => [
                        'payment' => [
                            'qrcode_original_path' => 'pix-qrcode',
                        ],
                    ],
                ],
            ])
        );
        $this->assertNull($sdk->lookupPixCopyPaste([]));
    }

    public function testLookupHelpersReturnEmptyWhenMissing(): void
    {
        $sdk = $this->sdk();

        $this->assertSame(0.0, $sdk->lookupAmount([]));
        $this->assertNull($sdk->lookupDigitableLine([]));
        $this->assertNull($sdk->lookupBarCode([]));
        $this->assertNull($sdk->lookupPixCopyPaste([]));
    }

    private function sdk(): Yapay
    {
        return new Yapay(new Store('token_account', Environment::sandbox()));
    }

    /**
     * @return array<string, mixed>
     */
    private function bankLookupPayload(): array
    {
        return [
            'data_response' => [
                'transaction' => [
                    'transaction_id' => 79690,
                    'status_name' => 'Aguardando Pagamento',
                    'date_transaction' => '2026-03-06 09:00:00',
                    'payment' => [
                        'payment_method_id' => 6,
                        'price_payment' => '150.50',
                        'linha_digitavel' => '23793.38128 60000.000003 00000.000400 1 84490000015050',
                        'bar_code' => '23791844900000150503381260000000000000000004',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pixLookupPayload(): array
    {
        return [
            'data_response' => [
                'transaction' => [
                    'transaction_id' => 79691,
                    'status_name' => 'Aguardando Pagamento',
                    'payment' => [
                        'payment_method_id' => 27,
                        'price_payment' => '80.00',
                        'qrcode_original_path' => '00020126580014br.gov.bcb.pix',
                    ],
                ],
            ],
        ];
    }
}
