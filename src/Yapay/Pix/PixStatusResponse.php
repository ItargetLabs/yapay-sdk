<?php

declare(strict_types=1);

namespace YapaySdk\Pix;

use DateTime;
use YapaySdk\PaymentStatus;

final class PixStatusResponse
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly string $tid,
        public readonly ?string $nsu,
        public readonly float $amount,
        public readonly ?string $authorizationCode,
        public readonly ?string $payerSolicitation,
        public readonly ?string $location,
        public readonly ?DateTime $occurrenceDate,
        public readonly ?DateTime $lowDate,
        public readonly ?string $pixCopyPaste,
        public readonly string $tokenTransaction = '',
        public readonly array $rawResponse = []
    ) {
    }
}
