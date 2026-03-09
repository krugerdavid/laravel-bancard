<?php

namespace KrugerDavid\Bancard\DTOs;

class BillingDetail
{
    public function __construct(
        public string $description,
        public string $amount,
        public int $ivaRate,
        public int $totalItems
    ) {}

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'amount' => $this->formatAmount($this->amount),
            'iva_rate' => $this->ivaRate,
            'total_items' => $this->totalItems,
        ];
    }

    protected function formatAmount(string|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
