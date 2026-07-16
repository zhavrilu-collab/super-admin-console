@extends('layouts.guest-platform')

@section('title', 'Sigurnost')

@section('content')
@if (session('status'))
    <div class="alert alert-success small">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning small">{{ session('warning') }}</div>
@endif

<p class="text-muted small mb-3">Dvofaktorska autentifikacija za {{ $user->email }}</p>

@if(is_array($recoveryCodes) && $recoveryCodes !== [])
    <div class="alert alert-warning small">
        <strong>Rezervni kôdovi</strong> — spremi ih; prikazuju se samo jednom.
        <ul class="mb-0 mt-2 font-monospace">
            @foreach($recoveryCodes as $recoveryCode)
                <li>{{ $recoveryCode }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($twoFactorEnabled)
    <p class="small text-success mb-3">2FA je uključena.</p>

    <form method="POST" action="{{ route('platform.two-factor.setup.destroy') }}">
        @csrf
        @method('DELETE')

        <div class="mb-3">
            <label class="form-label">Lozinka</label>
            <input type="password" name="password" class="form-control @error('disable') is-invalid @enderror" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Kôd ili rezervni kôd</label>
            <input type="text" name="code" class="form-control @error('disable') is-invalid @enderror" required>
            @error('disable')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-outline-danger btn-sm">Isključi 2FA</button>
    </form>
@elseif($setupSecret)
    <p class="text-muted small">Skeniraj QR kôd, zatim unesi potvrdni kôd.</p>
    @if($qrCode)
        <div class="mb-3">{!! $qrCode !!}</div>
    @endif
    <p class="small text-muted">Ručni ključ: <code>{{ $setupSecret }}</code></p>

    <form method="POST" action="{{ route('platform.two-factor.setup.confirm') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Potvrdni kôd</label>
            <input type="text" name="code" inputmode="numeric"
                   class="form-control @error('code') is-invalid @enderror" required autofocus>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-dark">Aktiviraj 2FA</button>
    </form>
@else
    <p class="text-muted small mb-3">
        Opcionalna zaštita: pri svakoj prijavi (lozinka ili Google/Microsoft) traži se kôd iz autentifikatora.
    </p>
    <form method="POST" action="{{ route('platform.two-factor.setup.begin') }}">
        @csrf
        <button type="submit" class="btn btn-dark">Pokreni postavljanje</button>
    </form>
@endif
@endsection
