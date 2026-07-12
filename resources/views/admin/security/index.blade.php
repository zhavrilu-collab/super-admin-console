@extends('layouts.admin')

@section('title', 'Sigurnost')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Sigurnost</h1>
    <p class="text-muted mb-0">Dvofaktorska autentifikacija (2FA) za super-admin pristup.</p>
</div>

@if($twoFactorRequired && ! $twoFactorEnabled)
    <div class="alert alert-warning">
        Dvofaktorska autentifikacija je <strong>obavezna</strong> prije pristupa konzoli. Postavi 2FA ispod.
    </div>
@endif

@if(is_array($recoveryCodes) && $recoveryCodes !== [])
    <div class="alert alert-warning">
        <h2 class="h6 fw-semibold">Rezervni kôdovi</h2>
        <p class="small mb-2">Spremi ove kôdove na sigurno mjesto. Prikazuju se samo jednom.</p>
        <ul class="mb-0 small font-monospace">
            @foreach($recoveryCodes as $recoveryCode)
                <li>{{ $recoveryCode }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        @if($twoFactorEnabled)
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">2FA je uključena</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Pri svakoj prijavi trebat će ti kôd iz autentifikatora (Google Authenticator, Authy, itd.).
                    </p>

                    @if(! $twoFactorRequired)
                        <form method="POST" action="{{ route('admin.two-factor.destroy') }}">
                            @csrf
                            @method('DELETE')

                            <div class="mb-3">
                                <label class="form-label">Lozinka</label>
                                <input type="password" name="password" class="form-control @error('disable') is-invalid @enderror" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kôd iz autentifikatora ili rezervni kôd</label>
                                <input type="text" name="code" class="form-control @error('disable') is-invalid @enderror" required>
                                @error('disable')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <button type="submit" class="btn btn-outline-danger">Isključi 2FA</button>
                        </form>
                    @else
                        <p class="text-muted small mb-0">2FA je obavezna za sve super-admin račune i ne može se isključiti.</p>
                    @endif
                </div>
            </div>
        @elseif($setupSecret)
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Potvrdi 2FA</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted">Skeniraj QR kôd autentifikatorom, zatim unesi generirani kôd.</p>

                    @if($qrCode)
                        <div class="mb-3">{!! $qrCode !!}</div>
                    @endif

                    <p class="small text-muted">Ručni ključ: <code>{{ $setupSecret }}</code></p>

                    <form method="POST" action="{{ route('admin.two-factor.confirm') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Potvrdni kôd</label>
                            <input type="text" name="code" inputmode="numeric"
                                   class="form-control @error('code') is-invalid @enderror" required autofocus>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-dark">Aktiviraj 2FA</button>
                    </form>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Uključi 2FA</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        @if($twoFactorRequired)
                            Obavezno za sve super-admin račune. Bez 2FA nema pristupa konzoli.
                        @else
                            Preporučeno za sve super-admin račune. Dodatni korak pri prijavi štiti konzolu čak i ako netko sazna lozinku.
                        @endif
                    </p>
                    <form method="POST" action="{{ route('admin.two-factor.begin') }}">
                        @csrf
                        <button type="submit" class="btn btn-dark">Pokreni postavljanje</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
