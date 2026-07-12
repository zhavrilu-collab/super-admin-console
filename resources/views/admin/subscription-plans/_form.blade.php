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
    <label class="form-label">Limit članova</label>
    <input type="number" name="member_limit" min="1" class="form-control @error('member_limit') is-invalid @enderror"
           value="{{ old('member_limit', $plan->member_limit ?? '') }}" placeholder="Prazno = neograničeno">
    @error('member_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
<h2 class="h6 mb-3">Web i domene</h2>

<div class="form-check mb-2">
    <input type="checkbox" name="subdomain" value="1" class="form-check-input" id="plan-subdomain"
           @checked(old('subdomain', $plan->subdomain ?? false))>
    <label class="form-check-label" for="plan-subdomain">Poddomena (npr. udruga.app.test)</label>
</div>

<div class="form-check mb-2">
    <input type="checkbox" name="custom_domain" value="1" class="form-check-input" id="plan-custom-domain"
           @checked(old('custom_domain', $plan->custom_domain ?? false))>
    <label class="form-check-label" for="plan-custom-domain">Vlastita domena</label>
</div>

<div class="form-check mb-2">
    <input type="checkbox" name="editable_sections" value="1" class="form-check-input" id="plan-editable-sections"
           @checked(old('editable_sections', $plan->editable_sections ?? false))>
    <label class="form-check-label" for="plan-editable-sections">Uređivanje sekcija javnog weba</label>
</div>

<div class="form-check mb-3">
    <input type="checkbox" name="cookie_banner" value="1" class="form-check-input" id="plan-cookie-banner"
           @checked(old('cookie_banner', $plan->cookie_banner ?? false))>
    <label class="form-check-label" for="plan-cookie-banner">Cookie banner na javnom webu</label>
</div>
