<?php

namespace LBHurtado\Voucher\Reports\Resolvers;

use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\Voucher\Models\Voucher;

class VoucherListResolver implements ReportResolverInterface
{
    public function resolve(
        array $filters = [],
        ?string $sort = null,
        string $sortDirection = 'desc',
        int $perPage = 20,
        int $page = 1,
    ): array {
        $query = Voucher::query();

        // Scope to authenticated user's vouchers
        if ($user = auth()->user()) {
            $query->where('owner_id', $user->id)
                ->where('owner_type', get_class($user));
        }

        // Status filter — display-status-aware query logic
        if (! empty($filters['status'])) {
            match ($filters['status']) {
                'active' => $query->where('state', 'active')
                    ->whereNull('redeemed_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                    }),
                'redeemed' => $query->whereNotNull('redeemed_at'),
                'expired' => $query->where('expires_at', '<', now())
                    ->where('state', 'active')
                    ->whereNull('redeemed_at'),
                'locked' => $query->where('state', 'locked'),
                'cancelled' => $query->where('state', 'cancelled'),
                'closed' => $query->where('state', 'closed'),
                default => null,
            };
        }

        // Voucher type filter
        if (! empty($filters['voucher_type'])) {
            $query->where('voucher_type', $filters['voucher_type']);
        }

        // Search filter — prefix match on code
        if (! empty($filters['search'])) {
            $query->where('code', 'like', strtoupper(trim($filters['search'])).'%');
        }

        // Sort
        $query->orderBy($sort ?? 'created_at', $sortDirection);

        // Paginate
        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $rows = $paginator->getCollection()->map(function (Voucher $voucher) {
            return [
                'code' => $voucher->code,
                'amount' => $this->extractAmount($voucher),
                'target_amount' => $voucher->target_amount ? (float) $voucher->target_amount : null,
                'voucher_type' => $voucher->voucher_type?->value ?? 'redeemable',
                'currency' => $voucher->cash?->currency ?? 'PHP',
                'status' => $voucher->display_status,
                'state' => $voucher->state?->value,
                'expires_at' => $voucher->expires_at?->toIso8601String(),
                'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
                'created_at' => $voucher->created_at->toIso8601String(),
                'slice_mode' => $voucher->instructions->cash->slice_mode ?? null,
            ];
        })->toArray();

        return [
            'data' => $rows,
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Extract numeric amount based on voucher type.
     *
     * Consolidates the extractAmount logic previously duplicated
     * in PwaVoucherController's index(), search(), and show().
     */
    private function extractAmount(Voucher $voucher): float
    {
        $cashAmount = $voucher->cash?->amount;

        $raw = match ($voucher->voucher_type?->value) {
            'payable' => $voucher->target_amount ?? 0,
            default => $cashAmount,
        };

        // Handle Money objects (brick/money)
        if (is_object($raw) && method_exists($raw, 'getAmount')) {
            return $raw->getAmount()->toFloat();
        }

        return is_numeric($raw) ? (float) $raw : 0.0;
    }
}
