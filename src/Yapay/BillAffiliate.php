<?php

declare(strict_types=1);

namespace YapaySdk;

final class BillAffiliate
{
    public function __construct(
        public readonly string $accountEmail,
        public readonly ?int $percentage = null,
        public readonly ?float $commissionAmount = null,
        public readonly ?string $typeAffiliate = null
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'account_email' => $this->accountEmail,
        ];

        if ($this->percentage !== null) {
            $data['percentage'] = $this->percentage;
        }

        if ($this->commissionAmount !== null) {
            $data['commission_amount'] = $this->commissionAmount;
        }

        if ($this->typeAffiliate !== null) {
            $data['type_affiliate'] = $this->typeAffiliate;
        }

        return $data;
    }
}
