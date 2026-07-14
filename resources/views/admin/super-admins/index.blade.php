@extends('layouts.admin')

@section('title', 'Super-admin korisnici')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Super-admin korisnici</h1>
        <p class="text-muted mb-0">Upravljanje pristupom konzoli i dodjela uloga.</p>
    </div>
    <a href="{{ route('admin.super-admins.create') }}" class="btn btn-dark">Novi super-admin</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ime</th>
                    <th>E-mail</th>
                    <th>2FA</th>
                    <th>Kreiran</th>
                    <th class="text-end">Akcije</th>
                </tr>
            </thead>
            <tbody>
                @forelse($superAdmins as $admin)
                    <tr>
                        <td>{{ $admin->name }}</td>
                        <td>{{ $admin->email }}</td>
                        <td>
                            @if($admin->hasTwoFactorEnabled())
                                <span class="badge bg-success">Aktivna</span>
                            @else
                                <span class="badge bg-warning text-dark">Nije postavljena</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $admin->created_at?->format('d.m.Y.') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.super-admins.edit', $admin) }}" class="btn btn-sm btn-outline-secondary">Uredi</a>
                            @if($admin->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.super-admins.destroy', $admin) }}" class="d-inline" onsubmit="return confirm('Obrisati super-admin korisnika?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Obriši</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Nema super-admin korisnika.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
