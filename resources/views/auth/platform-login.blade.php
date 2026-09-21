@extends('layouts.guest-platform')

@section('title', $loginAppearance['heading'] ?? 'Prijava')

@section('content')
<div class="{{ ($loginAppearance['theme'] ?? 'platform') === 'platform' ? '' : 'kartica-kontejner' }}">
    @if(!empty($loginAppearance['lockup']))
        <img src="{{ asset($loginAppearance['lockup']) }}" alt="{{ $loginAppearance['lockup_alt'] }}" class="app-guest-lockup">
    @endif

    @if(($loginAppearance['theme'] ?? 'platform') !== 'platform')
        <h1 class="h4 text-tema mb-3">{{ $loginAppearance['heading'] }}</h1>
    @endif

    @if(!empty($loginAppearance['intro']))
        <p class="text-muted small">{{ $loginAppearance['intro'] }}</p>
    @endif

    @if(!empty($loginAppearance['show_app_badge']) && $applicationName)
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

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label" for="remember">Zapamti me</label>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn {{ ($loginAppearance['theme'] ?? 'platform') === 'platform' ? 'btn-dark' : 'btn-primary' }} w-100">
                {{ $loginAppearance['button_label'] ?? 'Prijavi se' }}
            </button>
        </div>
    </form>

    @if ($googleLoginUrl || $microsoftLoginUrl)
        <div class="position-relative my-4">
            <hr>
            <span class="position-absolute top-50 start-50 translate-middle px-2 small text-muted bg-white">ili</span>
        </div>

        <div class="d-grid gap-2">
            @if ($googleLoginUrl)
                <a href="{{ $googleLoginUrl }}" class="btn btn-outline-secondary btn-sm">Prijava s Googleom</a>
            @endif
            @if ($microsoftLoginUrl)
                <a href="{{ $microsoftLoginUrl }}" class="btn btn-outline-secondary btn-sm">Prijava s Microsoftom</a>
            @endif
        </div>
    @endif

    <p class="text-center mt-3 mb-0 small">
        <a href="{{ $loginAppearance['forgot_password_url'] }}">Zaboravili ste lozinku?</a>
    </p>
    @if(!empty($loginAppearance['register_url']))
        <p class="text-center mt-2 mb-0 small">
            {{ $loginAppearance['register_prompt'] }}
            <a href="{{ $loginAppearance['register_url'] }}">{{ $loginAppearance['register_cta'] }}</a>
        </p>
    @endif
</div>
@endsection
