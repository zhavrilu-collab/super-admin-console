<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\TenantListRequest;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class TenantListService
{
    public function paginateForApplication(?int $applicationId, TenantListRequest $request): LengthAwarePaginator
    {
        if ($applicationId === null) {
            return new Paginator([], 0, $request->perPage());
        }

        $query = Tenant::query()
            ->where('application_id', $applicationId);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query
            ->paginate($request->perPage())
            ->withQueryString();
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    private function applyFilters(Builder $query, TenantListRequest $request): void
    {
        if ($search = $request->searchQuery()) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('external_id', 'like', '%'.$search.'%');
            });
        }

        if ($status = $request->statusFilter()) {
            $query->where('status', $status);
        }

        if ($plan = $request->planFilterSlug()) {
            $query->where('plan', $plan);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    private function applySorting(Builder $query, TenantListRequest $request): void
    {
        $sort = $request->sortColumn();
        $direction = $request->sortDirection();

        if ($sort === 'synced_at') {
            $query->orderByRaw('synced_at IS NULL');
        }

        $query->orderBy($sort, $direction);

        if ($sort !== 'name') {
            $query->orderBy('name');
        }
    }
}
