<?php

namespace LBHurtado\Voucher\Reports\Resolvers;

use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\Voucher\Models\Voucher;

class RecentVouchersResolver implements ReportResolverInterface
{
    public function resolve(
        array $filters = [],
        ?string $sort = null,
        string $sortDirection = 'desc',
        int $perPage = 10,
        int $page = 1,
    ): array {
        $query = Voucher::query();

        // Scope to authenticated user's vouchers
        if ($user = auth()->user()) {
            $query->where('owner_id', $user->id)
                ->where('owner_type', get_class($user));
        }

        // Apply filters
        if (! empty($filters['voucher_type'])) {
            $query->where('voucher_type', $filters['voucher_type']);
        }

        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        // Sort
        $query->orderBy($sort ?? 'created_at', $sortDirection);

        // Paginate
        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $rows = $paginator->getCollection()->map(function (Voucher $voucher) {
            $amount = $voucher->cash?->amount;

            return [
                'code' => $voucher->code,
                'amount' => $amount ? (string) $amount : '—',
                'voucher_type' => $voucher->voucher_type?->value ?? '—',
                'state' => $voucher->display_status,
                'created_at' => $voucher->created_at?->format('Y-m-d H:i:s') ?? '—',
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
}
