<?php

declare(strict_types=1);

namespace YapaySdk\Tests\Unit;

use DateTime;
use PHPUnit\Framework\TestCase;
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
}
