<?php

namespace LBHurtado\Voucher\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class VouchersGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(protected Collection $vouchers) {}

    public function getVouchers(): Collection
    {
        return $this->vouchers;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('vouchers-generated'),
        ];
    }
}
