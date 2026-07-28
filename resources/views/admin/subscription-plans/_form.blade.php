@csrf

<div class="mb-3">
    <label class="form-label">Naziv</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $plan->name ?? '') }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Slug</label>
    <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror"
           value="{{ old('slug', $plan->slug ?? '') }}" required pattern="[a-z0-9\-]+">
    <div class="form-text">Samo mala slova, brojke i crtice (npr. standard).</div>
    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Boja oznake</label>
    <select name="badge_class" class="form-select @error('badge_class') is-invalid @enderror" required>
        @foreach($badgeOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('badge_class', $plan->badge_class ?? 'secondary') === $value)>
                {{ $label }}
            </option>
        @endforeach
    </select>
    @error('badge_class')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Redoslijed</label>
    <input type="number" name="sort_order" min="0" class="form-control @error('sort_order') is-invalid @enderror"
           value="{{ old('sort_order', $plan->sort_order ?? 0) }}">
    @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-check mb-3">
    <input type="checkbox" name="is_default" value="1" class="form-check-input" id="plan-is-default"
           @checked(old('is_default', $plan->is_default ?? false))>
    <label class="form-check-label" for="plan-is-default">Zadani paket za nove registracije</label>
</div>

<hr class="my-4">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h6 mb-0">Značajke paketa</h2>
    <a href="{{ route('admin.application-features.index') }}" class="small">Uredi katalog</a>
</div>

@php
    $planFeatures = old('features', isset($plan) ? $plan->featuresMap() : []);
@endphp

@forelse($featureCatalog as $feature)
    @if($feature->isLimit())
        <div class="mb-3">
            <label class="form-label" for="feature-{{ $feature->key }}">{{ $feature->label }}</label>
            <input type="number" name="features[{{ $feature->key }}]" id="feature-{{ $feature->key }}"
                   min="1" class="form-control @error('features.'.$feature->key) is-invalid @enderror"
                   value="{{ old('features.'.$feature->key, $planFeatures[$feature->key] ?? '') }}"
                   placeholder="Prazno = neograničeno">
            @if($feature->description)
                <div class="form-text">{{ $feature->description }}</div>
            @endif
            @error('features.'.$feature->key)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @else
        <div class="form-check mb-2">
            <input type="checkbox" name="features[{{ $feature->key }}]" value="1"
                   class="form-check-input" id="feature-{{ $feature->key }}"
                   @checked(old('features.'.$feature->key, (bool) ($planFeatures[$feature->key] ?? false)))>
            <label class="form-check-label" for="feature-{{ $feature->key }}">{{ $feature->label }}</label>
            @if($feature->description)
                <div class="form-text">{{ $feature->description }}</div>
            @endif
        </div>
    @endif
@empty
    <p class="text-muted small">Nema značajki u katalogu. <a href="{{ route('admin.application-features.index') }}">Dodaj značajke</a>.</p>
@endforelse

<hr class="my-4">
<h2 class="h6 mb-3">Stripe naplata</h2>

<div class="mb-3">
    <label class="form-label">Mjesečna cijena (EUR)</label>
    <input type="number" name="monthly_price" step="0.01" min="0"
           class="form-control @error('monthly_price') is-invalid @enderror"
           value="{{ old('monthly_price', isset($plan->monthly_price_cents) ? number_format($plan->monthly_price_cents / 100, 2, '.', '') : '') }}"
           placeholder="npr. 29.00">
    <div class="form-text">Izvor za MRR i automatsko kreiranje Stripe cijene.</div>
    @error('monthly_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@php($stripeConfigured = $stripeConfigured ?? false)
<div class="form-check mb-3">
    <input type="hidden" name="sync_stripe_catalog" value="0">
    <input class="form-check-input" type="checkbox" name="sync_stripe_catalog" value="1" id="sync-stripe-catalog"
           @checked(old('sync_stripe_catalog', $stripeConfigured))>
    <label class="form-check-label" for="sync-stripe-catalog">
        Automatski syncaj Product/Price u Stripe
    </label>
    <div class="form-text">
        @if($stripeConfigured)
            Pri spremanju kreira ili osvježava Stripe Product i mjesečni Price iz cijene iznad.
        @else
            Stripe nije konfiguriran — uključi ključeve u Postavkama.
        @endif
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Stripe Product ID</label>
    <input type="text" name="stripe_product_id" class="form-control @error('stripe_product_id') is-invalid @enderror"
           value="{{ old('stripe_product_id', $plan->stripe_product_id ?? '') }}" placeholder="prod_...">
    <div class="form-text">Opcionalno ručno; auto-sync popunjava ovo polje.</div>
    @error('stripe_product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Stripe Price ID</label>
    <input type="text" name="stripe_price_id" class="form-control @error('stripe_price_id') is-invalid @enderror"
           value="{{ old('stripe_price_id', $plan->stripe_price_id ?? '') }}" placeholder="price_...">
    <div class="form-text">Opcionalno ručno; auto-sync kreira novi Price ako se cijena promijeni.</div>
    @error('stripe_price_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
