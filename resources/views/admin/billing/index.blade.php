@extends('layouts.admin')

@section('title', 'Naplata i MRR')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Naplata i MRR</h1>
    @if($activeApplication)
        <p class="text-muted mb-0">
            Metrike pretplate za <strong>{{ $activeApplication->name }}</strong>.
            MRR se računa iz aktivnih Stripe pretplata i cijena paketa.
        </p>
    @else
        <p class="text-muted mb-0">Odaberite aplikaciju za pregled metrika.</p>
    @endif
</div>

@if($activeApplication)
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 border-start border-primary border-4">
                <div class="card-body">
                    <div class="text-muted small">MRR (mjesečno)</div>
                    <div class="display-6 fw-bold">{{ $billingMetrics->formatMoney($metrics['mrr_cents'], $metrics['currency']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">ARR (godišnje)</div>
                    <div class="display-6 fw-bold">{{ $billingMetrics->formatMoney($metrics['arr_cents'], $metrics['currency']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Procijenjeni LTV</div>
                    <div class="display-6 fw-bold">{{ $billingMetrics->formatMoney($metrics['estimated_ltv_cents'], $metrics['currency']) }}</div>
                    <div class="small text-muted">Na temelju prosječnog ARPU × {{ config('billing.metrics.default_ltv_months', 24) }} mj.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Aktivne pretplate</div>
                    <div class="h3 mb-0">{{ $metrics['active_subscriptions'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Nove (30 dana)</div>
                    <div class="h3 mb-0 text-success">{{ $metrics['new_subscriptions_30d'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Churn (30 dana)</div>
                    <div class="h3 mb-0 text-danger">{{ $metrics['churned_30d'] }}</div>
                    <div class="small text-muted">{{ number_format($metrics['churn_rate_percent'], 1, ',', '.') }}%</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Otvoreni dunning</div>
                    <div class="h3 mb-0 @if($metrics['open_dunning_cases'] > 0) text-warning @endif">{{ $metrics['open_dunning_cases'] }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($metrics['priced_plans_missing'] > 0)
        <div class="alert alert-warning">
            {{ $metrics['priced_plans_missing'] }} aktivnih pretplata nema postavljenu mjesečnu cijenu na paketu — nisu uključene u MRR.
            <a href="{{ route('admin.subscription-plans.index') }}">Postavi cijene paketa</a>.
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">MRR po paketu</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Paket</th>
                        <th>Aktivne pretplate</th>
                        <th class="text-end">MRR</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($metrics['plan_breakdown'] as $row)
                        <tr>
                            <td>{{ $row['name'] }} <code class="small">{{ $row['slug'] }}</code></td>
                            <td>{{ $row['subscriptions'] }}</td>
                            <td class="text-end fw-semibold">
                                {{ $billingMetrics->formatMoney($row['mrr_cents'], $metrics['currency']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Nema definiranih paketa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
