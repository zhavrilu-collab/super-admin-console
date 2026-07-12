@extends('layouts.admin')

@section('title', 'Aplikacije')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">SaaS aplikacije</h1>
        <p class="text-muted mb-0">Upravljanje proizvodima koje konzola nadzire.</p>
    </div>
    <a href="{{ route('admin.applications.create') }}" class="btn btn-dark">Nova aplikacija</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Naziv</th>
                    <th>Slug</th>
                    <th>Tenanata</th>
                    <th>API</th>
                    <th class="text-end">Akcije</th>
                </tr>
            </thead>
            <tbody>
                @forelse($applications as $application)
                    <tr>
                        <td class="fw-medium">{{ $application->name }}</td>
                        <td><code>{{ $application->slug }}</code></td>
                        <td>{{ $application->tenants_count }}</td>
                        <td>
                            @if($application->api_base_url)
                                <span class="badge bg-success">Povezano</span>
                            @else
                                <span class="badge bg-secondary">Nije postavljeno</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.applications.edit', $application) }}" class="btn btn-sm btn-outline-primary">Uredi</a>
                            @if($application->tenants_count === 0)
                                <form method="POST" action="{{ route('admin.applications.destroy', $application) }}" class="d-inline js-confirm-action"
                                      data-confirm="Obrisati aplikaciju {{ $application->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Obriši</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Nema aplikacija.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
