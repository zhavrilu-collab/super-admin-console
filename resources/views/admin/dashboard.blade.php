@extends('layouts.admin')

@section('title', 'Nadzorna ploča')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Nadzorna ploča</h1>
        @if($activeApplication)
            <p class="text-muted mb-0">
                Aktivna aplikacija: <strong>{{ $activeApplication->name }}</strong>
                @if($syncSupported ?? false)
                    <span class="mx-1">·</span>
                    @if($activeApplication->last_synced_at)
                        Zadnja sinkronizacija:
                        <strong>{{ $activeApplication->last_synced_at->format('d.m.Y. H:i') }}</strong>
                    @else
                        Još nije sinkronizirano
                    @endif
                @endif
            </p>
        @else
            <p class="text-muted mb-0">Nema registriranih aplikacija.</p>
        @endif
    </div>
    @if($activeApplication && ($syncSupported ?? false))
        <form method="POST" action="{{ route('admin.sync') }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
                Sinkroniziraj iz SaaS-a
            </button>
        </form>
    @endif
</div>

@if($activeApplication)
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="{{ $filters->dashboardUrl(['status' => null, 'plan' => null, 'page' => null]) }}"
               class="card border-0 shadow-sm h-100 text-decoration-none text-body @if($filters->isStatusFilterActive(null) && ! $filters->planFilterSlug()) border border-dark border-2 @endif">
                <div class="card-body">
                    <div class="text-muted small">Ukupno tenanata</div>
                    <div class="display-6 fw-bold">{{ $stats['total'] }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ $filters->statusFilterUrl(\App\Enums\TenantStatus::Active->value) }}"
               class="card border-0 shadow-sm h-100 border-start border-success border-4 text-decoration-none text-body @if($filters->isStatusFilterActive(\App\Enums\TenantStatus::Active->value)) border border-success border-2 @endif">
                <div class="card-body">
                    <div class="text-muted small">Aktivni</div>
                    <div class="display-6 fw-bold text-success">{{ $stats['active'] }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ $filters->statusFilterUrl(\App\Enums\TenantStatus::Suspended->value) }}"
               class="card border-0 shadow-sm h-100 border-start border-danger border-4 text-decoration-none text-body @if($filters->isStatusFilterActive(\App\Enums\TenantStatus::Suspended->value)) border border-danger border-2 @endif">
                <div class="card-body">
                    <div class="text-muted small">Suspendirani</div>
                    <div class="display-6 fw-bold text-danger">{{ $stats['suspended'] }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ $filters->statusFilterUrl(\App\Enums\TenantStatus::Pending->value) }}"
               class="card border-0 shadow-sm h-100 border-start border-warning border-4 text-decoration-none text-body @if($filters->isStatusFilterActive(\App\Enums\TenantStatus::Pending->value)) border border-warning border-2 @endif">
                <div class="card-body">
                    <div class="text-muted small">Na čekanju</div>
                    <div class="display-6 fw-bold text-warning">{{ $stats['pending'] }}</div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach($subscriptionPlans as $subscriptionPlan)
            <div class="col-md-4">
                <a href="{{ $filters->planFilterUrl($subscriptionPlan->slug) }}"
                   class="card border-0 shadow-sm h-100 text-decoration-none text-body @if($filters->isPlanFilterActive($subscriptionPlan->slug)) border border-{{ $subscriptionPlan->badge_class }} border-2 @endif">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <span class="badge bg-{{ $subscriptionPlan->badge_class }}">{{ $subscriptionPlan->name }}</span>
                        <span class="fs-4 fw-semibold">{{ $stats['plans'][$subscriptionPlan->slug] ?? 0 }}</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.dashboard') }}" class="row g-3 align-items-end js-dashboard-filters">
                @if($filters->filled('status'))
                    <input type="hidden" name="status" value="{{ $filters->input('status') }}">
                @endif
                @if($filters->filled('plan'))
                    <input type="hidden" name="plan" value="{{ $filters->input('plan') }}">
                @endif
                @if($filters->input('sort', 'name') !== 'name')
                    <input type="hidden" name="sort" value="{{ $filters->input('sort') }}">
                @endif
                @if($filters->input('direction', 'asc') !== 'asc')
                    <input type="hidden" name="direction" value="{{ $filters->input('direction') }}">
                @endif

                <div class="col-md-4">
                    <label class="form-label" for="filter-q">Pretraga</label>
                    <input type="search" id="filter-q" name="q" class="form-control"
                           value="{{ $filters->input('q') }}"
                           placeholder="Naziv, slug ili external ID">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filter-date-from">Registracija od</label>
                    <input type="date" id="filter-date-from" name="date_from" class="form-control"
                           value="{{ $filters->input('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filter-date-to">Registracija do</label>
                    <input type="date" id="filter-date-to" name="date_to" class="form-control"
                           value="{{ $filters->input('date_to') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filter-per-page">Po stranici</label>
                    <select id="filter-per-page" name="per_page" class="form-select">
                        @foreach([10, 25, 50, 100] as $perPageOption)
                            <option value="{{ $perPageOption }}" @selected((int) $filters->input('per_page', 25) === $perPageOption)>
                                {{ $perPageOption }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if($filters->hasAnyActiveState())
                    <div class="col-auto d-flex align-items-end">
                        <a href="{{ route('admin.dashboard') }}"
                           class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center px-3 mb-1"
                           title="Poništi sve filtere"
                           aria-label="Poništi sve filtere">&times;</a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 mb-0">Popis tenanata</h2>
                <div class="text-muted small">
                    Prikazano {{ $tenants->firstItem() ?? 0 }}–{{ $tenants->lastItem() ?? 0 }} od {{ $tenants->total() }}
                    @if($filters->hasActiveFilters() || $filters->statusFilter() || $filters->planFilterSlug())
                        (filtrirano)
                    @endif
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 js-bulk-toolbar">
                <span class="text-muted small js-bulk-selection-count">0 odabrano</span>
                <select class="form-select form-select-sm js-bulk-status-select" style="width: auto;" disabled>
                    <option value="active">Odobri odabrane</option>
                    <option value="suspended">Suspendiraj odabrane</option>
                    <option value="pending">Vrati na čekanje</option>
                </select>
                <button type="button" class="btn btn-sm btn-outline-primary js-bulk-status-submit" disabled>
                    Primijeni na odabrane
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 2.5rem;">
                            <input type="checkbox" class="form-check-input js-select-all-tenants" aria-label="Odaberi sve na stranici">
                        </th>
                        <th>
                            <a href="{{ $filters->sortUrl('name') }}" class="text-decoration-none text-body">
                                Naziv{{ $filters->sortIndicator('name') }}
                            </a>
                        </th>
                        <th>
                            <a href="{{ $filters->sortUrl('slug') }}" class="text-decoration-none text-body">
                                Slug{{ $filters->sortIndicator('slug') }}
                            </a>
                        </th>
                        <th>
                            <a href="{{ $filters->sortUrl('external_id') }}" class="text-decoration-none text-body">
                                External ID{{ $filters->sortIndicator('external_id') }}
                            </a>
                        </th>
                        <th>
                            <a href="{{ $filters->sortUrl('status') }}" class="text-decoration-none text-body">
                                Status{{ $filters->sortIndicator('status') }}
                            </a>
                        </th>
                        <th>
                            <a href="{{ $filters->sortUrl('plan') }}" class="text-decoration-none text-body">
                                Plan{{ $filters->sortIndicator('plan') }}
                            </a>
                        </th>
                        <th>
                            <a href="{{ $filters->sortUrl('created_at') }}" class="text-decoration-none text-body">
                                Registracija{{ $filters->sortIndicator('created_at') }}
                            </a>
                        </th>
                        <th>
                            <a href="{{ $filters->sortUrl('synced_at') }}" class="text-decoration-none text-body">
                                Zadnja sinkronizacija{{ $filters->sortIndicator('synced_at') }}
                            </a>
                        </th>
                        <th class="text-end">Akcije</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input js-tenant-select"
                                       name="tenant_ids[]" value="{{ $tenant->id }}"
                                       form="bulk-status-form" aria-label="Odaberi {{ $tenant->name }}">
                            </td>
                            <td class="fw-medium">
                                <a href="{{ route('admin.tenants.show', $tenant) }}" class="text-decoration-none">
                                    {{ $tenant->name }}
                                </a>
                            </td>
                            <td><code>{{ $tenant->slug }}</code></td>
                            <td>{{ $tenant->external_id ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $tenant->status->badgeClass() }}">
                                    {{ $tenant->status->label() }}
                                </span>
                            </td>
                            <td>
                                @php($planDef = $tenant->resolvedSubscriptionPlan())
                                <span class="badge bg-{{ $planDef?->badge_class ?? 'secondary' }}">
                                    {{ $planDef?->name ?? $tenant->plan }}
                                </span>
                            </td>
                            <td class="text-muted small">
                                {{ $tenant->created_at?->format('d.m.Y. H:i') ?? '—' }}
                            </td>
                            <td class="text-muted small">
                                {{ $tenant->synced_at?->format('d.m.Y. H:i') ?? '—' }}
                            </td>
                            <td class="text-end">
                                @include('admin.tenants.partials.actions', ['tenant' => $tenant])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                @if($filters->hasActiveFilters() || $filters->statusFilter() || $filters->planFilterSlug())
                                    Nema tenanata koji odgovaraju filterima.
                                @else
                                    Nema tenanata za ovu aplikaciju.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tenants->hasPages())
            <div class="card-footer bg-white">
                {{ $tenants->links() }}
            </div>
        @endif
    </div>

    <form id="bulk-status-form" method="POST" action="{{ route('admin.tenants.bulk-update-status') }}" class="d-none">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" class="js-bulk-status-hidden">
        @foreach($filters->query() as $queryKey => $queryValue)
            @if(is_scalar($queryValue))
                <input type="hidden" name="redirect[{{ $queryKey }}]" value="{{ $queryValue }}">
            @endif
        @endforeach
    </form>
@endif
@endsection
