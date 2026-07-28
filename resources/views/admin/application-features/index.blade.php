@extends('layouts.admin')

@section('title', 'Značajke paketa')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Značajke paketa</h1>
        <p class="text-muted mb-0">
            Katalog funkcionalnosti za <strong>{{ $activeApplication->name }}</strong>.
            Pri uređivanju paketa biraš koje su uključene.
        </p>
    </div>
    <a href="{{ route('admin.subscription-plans.index') }}" class="btn btn-outline-secondary btn-sm">Paketi</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h2 class="h6 mb-0">Katalog</h2>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ključ</th>
                            <th>Naziv</th>
                            <th>Tip</th>
                            <th>Red</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($features as $feature)
                            <tr>
                                <td><code>{{ $feature->key }}</code></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.application-features.update', $feature) }}" class="row g-2 align-items-end">
                                        @csrf
                                        @method('PATCH')
                                        <div class="col-12">
                                            <input type="text" name="label" class="form-control form-control-sm"
                                                   value="{{ old('label', $feature->label) }}" required>
                                        </div>
                                        <div class="col-8">
                                            <input type="text" name="description" class="form-control form-control-sm"
                                                   value="{{ old('description', $feature->description) }}"
                                                   placeholder="Opis (opcionalno)">
                                        </div>
                                        <div class="col-2">
                                            <input type="number" name="sort_order" class="form-control form-control-sm"
                                                   value="{{ old('sort_order', $feature->sort_order) }}">
                                        </div>
                                        <div class="col-2">
                                            <button class="btn btn-sm btn-outline-dark w-100" type="submit">Spremi</button>
                                        </div>
                                        @if($feature->isLimit())
                                            <div class="col-12">
                                                <input type="text" name="unit" class="form-control form-control-sm"
                                                       value="{{ old('unit', $feature->unit) }}" placeholder="Jedinica (npr. members)">
                                            </div>
                                        @endif
                                    </form>
                                </td>
                                <td><span class="badge bg-secondary">{{ $feature->type->label() }}</span></td>
                                <td>{{ $feature->sort_order }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.application-features.destroy', $feature) }}"
                                          onsubmit="return confirm('Obrisati značajku {{ $feature->key }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Obriši</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Nema značajki u katalogu.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h2 class="h6 mb-0">Nova značajka</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.application-features.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Ključ</label>
                        <input type="text" name="key" class="form-control @error('key') is-invalid @enderror"
                               value="{{ old('key') }}" required pattern="[a-z0-9_]+" placeholder="npr. sms_reminders">
                        <div class="form-text">Samo mala slova, brojke i underscore. Koristi ga modul za enforce.</div>
                        @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Naziv</label>
                        <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                               value="{{ old('label') }}" required>
                        @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tip</label>
                        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', 'boolean') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jedinica (za limite)</label>
                        <input type="text" name="unit" class="form-control" value="{{ old('unit') }}" placeholder="members">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Opis</label>
                        <input type="text" name="description" class="form-control" value="{{ old('description') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Redoslijed</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 100) }}">
                    </div>
                    <button type="submit" class="btn btn-dark">Dodaj</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
