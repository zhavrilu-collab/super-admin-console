@extends('layouts.admin')

@section('title', 'Novi super-admin')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Novi super-admin</h1>
    <p class="text-muted mb-0">Kreirajte novi račun s pristupom konzoli.</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.super-admins.store') }}">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Ime</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Lozinka</label>
                <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Potvrda lozinke</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-dark">Spremi</button>
                <a href="{{ route('admin.super-admins.index') }}" class="btn btn-outline-secondary">Odustani</a>
            </div>
        </form>
    </div>
</div>
@endsection
