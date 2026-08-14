<?php

declare(strict_types=1);

namespace YapaySdk\Bank;

use YapaySdk\PaymentStatus;

final class BankResponse
{
    public function __construct(
        public readonly string $tid,
        public readonly string $tokenTransaction,
        public readonly PaymentStatus $status,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $digitableLine,
        public readonly string $barCode,
        public readonly string $url,
        public readonly string $hash,
        public readonly ?string $authorizationCode = null,
        public readonly array $gatewayResponse = []
    ) {
    }
}
