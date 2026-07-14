@extends('layouts.admin')

@section('title', 'Uredi super-admin')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Uredi super-admin</h1>
    <p class="text-muted mb-0">{{ $superAdmin->email }}</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.super-admins.update', $superAdmin) }}">
            @csrf
            @method('PATCH')

            <div class="mb-3">
                <label for="name" class="form-label">Ime</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $superAdmin->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $superAdmin->email) }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Nova lozinka</label>
                <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror">
                <div class="form-text">Ostavite prazno ako ne mijenjate lozinku.</div>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Potvrda lozinke</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control">
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-dark">Spremi</button>
                <a href="{{ route('admin.super-admins.index') }}" class="btn btn-outline-secondary">Odustani</a>
            </div>
        </form>
    </div>
</div>
@endsection
