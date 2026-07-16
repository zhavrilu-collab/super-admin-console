@extends('layouts.guest-platform')

@section('title', 'Prijava')

@section('content')
@if ($applicationName)
    <p class="text-muted small text-center mb-3">Pristup aplikaciji: <strong>{{ $applicationName }}</strong></p>
@endif

<form method="POST" action="{{ route('platform.login.store') }}">
    @csrf

    @if ($applicationSlug)
        <input type="hidden" name="application_slug" value="{{ $applicationSlug }}">
    @endif

    <div class="mb-3">
        <label for="email" class="form-label">E-mail</label>
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

    <div class="d-grid">
        <button type="submit" class="btn btn-dark">Prijava</button>
    </div>
</form>

@if ($googleLoginUrl || $microsoftLoginUrl)
    <div class="position-relative my-4">
        <hr>
        <span class="position-absolute top-50 start-50 translate-middle bg-white px-2 small text-muted">ili</span>
    </div>

    <div class="d-grid gap-2">
        @if ($googleLoginUrl)
            <a href="{{ $googleLoginUrl }}" class="btn btn-outline-secondary btn-sm">Google prijava</a>
        @endif
        @if ($microsoftLoginUrl)
            <a href="{{ $microsoftLoginUrl }}" class="btn btn-outline-secondary btn-sm">Microsoft prijava</a>
        @endif
    </div>
@endif
@endsection
