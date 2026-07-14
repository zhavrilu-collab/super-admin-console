@extends('layouts.admin')

@section('title', 'Postavke')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Postavke konzole</h1>
    <p class="text-muted mb-0">E-mail, Stripe naplata i webhook parametri.</p>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">E-mail (SMTP)</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.settings.mail') }}">
                    @csrf
                    @method('PATCH')

                    <div class="mb-3">
                        <label class="form-label">Mailer</label>
                        <select name="mail_mailer" class="form-select @error('mail_mailer') is-invalid @enderror">
                            <option value="log" @selected(old('mail_mailer', $mail['mail_mailer']) === 'log')>Log (razvoj)</option>
                            <option value="smtp" @selected(old('mail_mailer', $mail['mail_mailer']) === 'smtp')>SMTP (produkcija)</option>
                        </select>
                        @error('mail_mailer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SMTP host</label>
                        <input type="text" name="mail_host" class="form-control @error('mail_host') is-invalid @enderror"
                               value="{{ old('mail_host', $mail['mail_host']) }}">
                        @error('mail_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Port</label>
                            <input type="number" name="mail_port" class="form-control @error('mail_port') is-invalid @enderror"
                                   value="{{ old('mail_port', $mail['mail_port']) }}">
                            @error('mail_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Enkripcija</label>
                            <select name="mail_encryption" class="form-select">
                                <option value="tls" @selected(old('mail_encryption', $mail['mail_encryption']) === 'tls')>TLS</option>
                                <option value="ssl" @selected(old('mail_encryption', $mail['mail_encryption']) === 'ssl')>SSL</option>
                                <option value="" @selected(old('mail_encryption', $mail['mail_encryption']) === '')>Bez</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SMTP korisnik</label>
                        <input type="text" name="mail_username" class="form-control"
                               value="{{ old('mail_username', $mail['mail_username']) }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SMTP lozinka</label>
                        <input type="password" name="mail_password" class="form-control" autocomplete="new-password"
                               placeholder="{{ $hasMailPassword ? '•••••••• (ostavi prazno da zadržiš)' : '' }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">From adresa</label>
                        <input type="email" name="mail_from_address" class="form-control @error('mail_from_address') is-invalid @enderror"
                               value="{{ old('mail_from_address', $mail['mail_from_address']) }}" required>
                        @error('mail_from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">From naziv</label>
                        <input type="text" name="mail_from_name" class="form-control @error('mail_from_name') is-invalid @enderror"
                               value="{{ old('mail_from_name', $mail['mail_from_name']) }}" required>
                        @error('mail_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <hr>

                    <h3 class="h6 mb-3">Stripe naplata</h3>

                    @if($stripeConfigured)
                        <div class="alert alert-success py-2 small mb-3">Stripe je konfiguriran (secret key postavljen).</div>
                    @else
                        <div class="alert alert-warning py-2 small mb-3">Stripe nije konfiguriran — unesite secret key ili postavite <code>STRIPE_SECRET_KEY</code> u <code>.env</code>.</div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">Publishable key</label>
                        <input type="text" name="stripe_publishable_key" class="form-control @error('stripe_publishable_key') is-invalid @enderror"
                               value="{{ old('stripe_publishable_key', $billing['stripe_publishable_key']) }}" placeholder="pk_test_...">
                        @error('stripe_publishable_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Secret key</label>
                        <input type="password" name="stripe_secret_key" class="form-control" autocomplete="new-password"
                               placeholder="{{ $hasStripeSecretKey ? '•••••••• (ostavi prazno da zadržiš)' : 'sk_test_...' }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Webhook signing secret</label>
                        <input type="password" name="stripe_webhook_secret" class="form-control" autocomplete="new-password"
                               placeholder="{{ $hasStripeWebhookSecret ? '•••••••• (ostavi prazno da zadržiš)' : 'whsec_...' }}">
                        <div class="form-text">Endpoint: <code>{{ url('/api/webhooks/stripe') }}</code></div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label">Webhook tajni ključ (dolazni)</label>
                        <input type="password" name="webhook_secret" class="form-control" autocomplete="new-password"
                               placeholder="{{ $hasWebhookSecret ? '•••••••• (ostavi prazno da zadržiš)' : 'Isti ključ kao u SaaS aplikaciji' }}">
                        <div class="form-text">SaaS aplikacije koriste ovaj Bearer token pri registraciji tenanta.</div>
                    </div>

                    <div class="form-text mb-3">
                        Super-admini primaju e-mail kad se registrira novi tenant na čekanju (webhook ili sync).
                        U razvoju koristi mailer <strong>Log</strong> — poruke se zapisuju u <code>storage/logs/laravel.log</code>.
                    </div>

                    <button type="submit" class="btn btn-dark">Spremi postavke</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
