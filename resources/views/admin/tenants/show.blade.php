@extends('layouts.admin')

@section('title', $tenant->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.dashboard') }}" class="text-decoration-none small">&larr; Natrag na nadzornu ploču</a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mt-2">
        <div>
            <h1 class="h3 mb-1">{{ $tenant->name }}</h1>
            <p class="text-muted mb-0">
                Tenant u aplikaciji <strong>{{ $tenant->application->name }}</strong>
            </p>
        </div>
        <div>
            @include('admin.tenants._moderation-actions', ['tenant' => $tenant])
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Status</div>
                <span class="badge bg-{{ $tenant->status->badgeClass() }} fs-6">
                    {{ $tenant->status->label() }}
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Plan pretplate</div>
                @php($planDef = $tenant->resolvedSubscriptionPlan())
                <span class="badge bg-{{ $planDef?->badge_class ?? 'secondary' }} fs-6">
                    {{ $planDef?->name ?? $tenant->plan }}
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Zadnja sinkronizacija</div>
                <div class="fw-semibold">
                    {{ $tenant->synced_at?->format('d.m.Y. H:i') ?? '—' }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h2 class="h6 mb-0">Meta-podaci</h2>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Slug</dt>
                    <dd class="col-sm-8"><code>{{ $tenant->slug }}</code></dd>

                    <dt class="col-sm-4 text-muted">External ID</dt>
                    <dd class="col-sm-8">{{ $tenant->external_id ?? '—' }}</dd>

                    <dt class="col-sm-4 text-muted">Aplikacija</dt>
                    <dd class="col-sm-8">{{ $tenant->application->name }}</dd>

                    <dt class="col-sm-4 text-muted">Sync driver</dt>
                    <dd class="col-sm-8">{{ $tenant->application->sync_driver ?? '—' }}</dd>

                    <dt class="col-sm-4 text-muted">Kreiran</dt>
                    <dd class="col-sm-8">{{ $tenant->created_at?->format('d.m.Y. H:i') ?? '—' }}</dd>

                    <dt class="col-sm-4 text-muted">Ažuriran</dt>
                    <dd class="col-sm-8">{{ $tenant->updated_at?->format('d.m.Y. H:i') ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h2 class="h6 mb-0">Poveznice</h2>
            </div>
            <div class="card-body">
                @if($saasUrl)
                    <p class="mb-2 d-flex flex-wrap gap-2">
                        <a href="{{ $saasUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                            Otvori u SaaS aplikaciji
                        </a>
                        @if($tenant->status === \App\Enums\TenantStatus::Active)
                            <form method="POST" action="{{ route('admin.tenants.impersonate', $tenant) }}" class="js-confirm-action d-inline" data-confirm="Ući u tenant {{ $tenant->name }} kao support?">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-sm">Uđi kao tenant</button>
                            </form>
                        @endif
                    </p>
                    <p class="small text-muted mb-0">
                        <code>{{ $saasUrl }}</code>
                    </p>
                @else
                    <p class="text-muted mb-0">
                        SaaS URL nije dostupan — provjerite API URL aplikacije u postavkama.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Povijest moderacije</h2>
        <a href="{{ route('admin.audit.index') }}" class="small text-decoration-none">Cijeli audit log</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Datum</th>
                    <th>Korisnik</th>
                    <th>Akcija</th>
                    <th>Detalji</th>
                </tr>
            </thead>
            <tbody>
                @forelse($auditLogs as $log)
                    <tr>
                        <td class="text-muted small text-nowrap">
                            {{ $log->created_at->format('d.m.Y. H:i') }}
                        </td>
                        <td>
                            @if($log->user)
                                <span class="fw-medium">{{ $log->user->name }}</span>
                                <div class="text-muted small">{{ $log->user->email }}</div>
                            @else
                                <span class="fw-medium text-muted">Sustav</span>
                                <div class="text-muted small">automatska akcija</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-secondary">{{ $log->action->label() }}</span>
                        </td>
                        <td>{{ $log->summary() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            Nema zabilježenih akcija za ovog tenanta.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($auditLogs->hasPages())
        <div class="card-footer bg-white">
            {{ $auditLogs->links() }}
        </div>
    @endif
</div>
@endsection
