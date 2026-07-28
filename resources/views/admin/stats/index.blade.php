@extends('layouts.admin')

@section('title', 'Statistika')

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('admin.stats.index') }}">Statistika</a></li>
            @if($metric && $value)
                <li class="breadcrumb-item active" aria-current="page">{{ $metricLabel }}: {{ $valueLabel }}</li>
            @endif
        </ol>
    </nav>
    <h1 class="h3 mb-1">Statistika</h1>
    <p class="text-muted mb-0">
        Pregled tenanata i značajki za <strong>{{ $activeApplication->name }}</strong>
        ({{ $aggregates['total_tenants'] }} tenanata).
        Klikni na segment grafa za listu tenanata.
    </p>
</div>

@if($drillDownTenants !== null)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h6 mb-0">{{ $metricLabel }}: {{ $valueLabel }}</h2>
                <div class="small text-muted">{{ $drillDownTenants->count() }} tenanata</div>
            </div>
            <a href="{{ route('admin.stats.index') }}" class="btn btn-sm btn-outline-secondary">Nazad na grafikone</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ime</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Plan</th>
                        <th>Sinkronizirano</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($drillDownTenants as $tenant)
                        <tr>
                            <td>
                                <a href="{{ route('admin.tenants.show', $tenant) }}">{{ $tenant->name }}</a>
                            </td>
                            <td><code>{{ $tenant->slug }}</code></td>
                            <td>{{ $tenant->status }}</td>
                            <td><code>{{ $tenant->plan }}</code></td>
                            <td class="small text-muted">
                                {{ $tenant->synced_at?->format('d.m.Y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Nema tenanata u ovom bucketu.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Tenanti po planu</h2>
            </div>
            <div class="card-body">
                <canvas id="chart-by-plan" height="220"
                        data-metric="plan"
                        data-labels='@json(collect($aggregates["tenants_by_plan"])->pluck("label"))'
                        data-keys='@json(collect($aggregates["tenants_by_plan"])->pluck("key"))'
                        data-values='@json(collect($aggregates["tenants_by_plan"])->pluck("count"))'></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Tenanti po statusu</h2>
            </div>
            <div class="card-body">
                <canvas id="chart-by-status" height="220"
                        data-metric="status"
                        data-labels='@json(collect($aggregates["tenants_by_status"])->pluck("label"))'
                        data-keys='@json(collect($aggregates["tenants_by_status"])->pluck("key"))'
                        data-values='@json(collect($aggregates["tenants_by_status"])->pluck("count"))'></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Feature coverage</h2>
            </div>
            <div class="card-body">
                <canvas id="chart-by-feature" height="220"
                        data-metric="feature"
                        data-labels='@json(collect($aggregates["feature_coverage"])->map(fn ($row) => $row["label"]." (".$row["percent"]."%)"))'
                        data-keys='@json(collect($aggregates["feature_coverage"])->pluck("key"))'
                        data-values='@json(collect($aggregates["feature_coverage"])->pluck("count"))'></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">MRR po planu</h2>
            </div>
            <div class="card-body">
                <canvas id="chart-by-mrr" height="220"
                        data-metric="mrr"
                        data-labels='@json(collect($aggregates["mrr_by_plan"])->pluck("label"))'
                        data-keys='@json(collect($aggregates["mrr_by_plan"])->pluck("key"))'
                        data-values='@json(collect($aggregates["mrr_by_plan"])->map(fn ($row) => round($row["mrr_cents"] / 100, 2)))'></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
(() => {
    const palette = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#0dcaf0', '#fd7e14', '#20c997'];

    function makePie(canvas) {
        if (!canvas) return;
        const labels = JSON.parse(canvas.dataset.labels || '[]');
        const keys = JSON.parse(canvas.dataset.keys || '[]');
        const values = JSON.parse(canvas.dataset.values || '[]');
        const metric = canvas.dataset.metric;

        const chart = new Chart(canvas, {
            type: 'pie',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map((_, i) => palette[i % palette.length]),
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                },
                onClick: (_event, elements) => {
                    if (!elements.length) return;
                    const index = elements[0].index;
                    const value = keys[index];
                    if (!value) return;
                    const url = new URL(@json(route('admin.stats.index')), window.location.origin);
                    url.searchParams.set('metric', metric);
                    url.searchParams.set('value', value);
                    window.location.href = url.toString();
                },
            },
        });

        return chart;
    }

    makePie(document.getElementById('chart-by-plan'));
    makePie(document.getElementById('chart-by-status'));
    makePie(document.getElementById('chart-by-feature'));
    makePie(document.getElementById('chart-by-mrr'));
})();
</script>
@endpush
