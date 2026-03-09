<?php

namespace KrugerDavid\Bancard\DTOs;

class Billing
{
    public function __construct(
        public ?string $clientRuc,
        public string $clientName,
        public string $clientEmail,
        public string $commerceStamp,
        public string $commerceExpeditionPoint,
        public string $commerceEstablishment,
        /** @var BillingDetail[] */
        public array $details
    ) {}

    public function toArray(): array
    {
        return [
            'client_ruc' => $this->clientRuc,
            'client_name' => $this->clientName,
            'client_email' => $this->clientEmail,
            'commerce_stamp' => $this->commerceStamp,
            'commerce_expedition_point' => $this->commerceExpeditionPoint,
            'commerce_establishment' => $this->commerceEstablishment,
            'details' => array_map(fn (BillingDetail $d) => $d->toArray(), $this->details),
        ];
    }
}
