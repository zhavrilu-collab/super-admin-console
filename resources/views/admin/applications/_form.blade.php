@csrf

<div class="mb-3">
    <label class="form-label">Naziv</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $application->name ?? '') }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Slug</label>
    <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror"
           value="{{ old('slug', $application->slug ?? '') }}" required
           pattern="[a-z0-9\-]+" placeholder="npr. opg-saas">
    <div class="form-text">Samo mala slova, brojke i crtice.</div>
    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Opis</label>
    <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $application->description ?? '') }}</textarea>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Sync driver</label>
    <select name="sync_driver" class="form-select @error('sync_driver') is-invalid @enderror">
        @foreach($drivers as $value => $label)
            <option value="{{ $value }}" @selected(old('sync_driver', $application->sync_driver ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('sync_driver')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">API base URL</label>
    <input type="url" name="api_base_url" class="form-control @error('api_base_url') is-invalid @enderror"
           value="{{ old('api_base_url', $application->api_base_url ?? '') }}"
           placeholder="http://127.0.0.1:8000">
    @error('api_base_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">API sync ključ</label>
    <input type="password" name="api_sync_key" class="form-control" autocomplete="new-password"
           placeholder="{{ isset($application) && $application->api_sync_key ? '•••••••• (ostavi prazno da zadržiš)' : '' }}">
</div>
