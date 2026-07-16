@extends('layouts.guest-platform')

@section('title', '2FA provjera')

@section('content')
<p class="text-muted small mb-3">
    Unesi 6-znamenkasti kôd iz autentifikatora ili rezervni kôd.
</p>

<form method="POST" action="{{ route('platform.two-factor.login.store') }}">
    @csrf

    <div class="mb-3">
        <label for="code" class="form-label">Kôd iz autentifikatora</label>
        <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
               class="form-control @error('code') is-invalid @enderror" autofocus>
        @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="recovery_code" class="form-label">Rezervni kôd</label>
        <input id="recovery_code" type="text" name="recovery_code"
               class="form-control @error('recovery_code') is-invalid @enderror" autocomplete="off">
        @error('recovery_code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-grid">
        <button type="submit" class="btn btn-dark">Potvrdi</button>
    </div>
</form>
@endsection
