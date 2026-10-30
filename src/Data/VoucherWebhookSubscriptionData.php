<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class VoucherWebhookSubscriptionData extends Data
{
    /** @param list<string> $events */
    public function __construct(public string $url, public array $events) {}
}
