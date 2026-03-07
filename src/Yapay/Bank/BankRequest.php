<?php

declare(strict_types=1);

namespace YapaySdk\Bank;

use DateTime;
use YapaySdk\BillAffiliate;
use YapaySdk\Customer;

final class BankRequest
{
    /**
     * @param BillAffiliate[] $affiliates
     */
    public function __construct(
        public float $amount,
        public string $currency,
        public Customer $customer,
        public string $description,
        public DateTime $dueDate,
        public ?string $number = null,
        public array $metadata = [],
        public array $affiliates = []
    ) {
    }
}
