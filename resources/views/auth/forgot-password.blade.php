@extends('layouts.guest-bootstrap')

@section('title', 'Zaboravljena lozinka')

@section('content')
<p class="text-muted small mb-4">
    Unesite e-mail adresu s kojom ste se registrirali. Poslat ćemo vam link za postavljanje nove lozinke.
</p>

@if (session('status'))
    <div class="alert alert-success small">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('password.email') }}">
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}"
               class="form-control @error('email') is-invalid @enderror" required autofocus>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-grid gap-2">
        <button type="submit" class="btn btn-dark">Pošalji link za reset</button>
        <a href="{{ route('login') }}" class="btn btn-link btn-sm">Natrag na prijavu</a>
    </div>
</form>
@endsection
