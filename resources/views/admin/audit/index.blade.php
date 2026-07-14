@extends('layouts.admin')

@section('title', 'Audit log')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Audit log</h1>
    @if($activeApplication)
        <p class="text-muted mb-0">
            Povijest moderacijskih akcija za <strong>{{ $activeApplication->name }}</strong>.
        </p>
    @else
        <p class="text-muted mb-0">Odaberite aplikaciju za pregled audit zapisa.</p>
    @endif
</div>

@if($activeApplication)
    <div class="card border-0 shadow-sm">
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
                                Nema zabilježenih akcija za ovu aplikaciju.
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
@endif
@endsection
