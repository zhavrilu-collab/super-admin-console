@extends('layouts.guest-bootstrap')

@section('title', 'Prijava')

@section('content')
@if (session('status'))
    <div class="alert alert-success small">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('login') }}">
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}"
               class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Lozinka</label>
        <input id="password" type="password" name="password"
               class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="remember_me" name="remember">
        <label class="form-check-label" for="remember_me">Zapamti me</label>
    </div>

    <div class="d-grid">
        <button type="submit" class="btn btn-dark">Prijava</button>
    </div>

    <p class="text-center mt-3 mb-0 small">
        <a href="{{ route('password.request') }}">Zaboravili ste lozinku?</a>
    </p>
</form>
@endsection
