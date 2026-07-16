@extends('layouts.guest-platform')

@section('title', 'Odabir organizacije')

@section('content')
<p class="text-muted small mb-3">Imate pristup više organizacija. Odaberite koju želite otvoriti.</p>

<form method="POST" action="{{ route('platform.pick.store') }}">
    @csrf

    <div class="list-group mb-3">
        @foreach ($workspaces as $workspace)
            @php
                $application = $applications->get($workspace['application_slug']);
                $workspaceKey = $workspace['application_slug'].':'.($workspace['tenant']['external_id'] ?? '');
            @endphp
            <label class="list-group-item list-group-item-action d-flex gap-3 align-items-start">
                <input class="form-check-input mt-1" type="radio" name="workspace_key" value="{{ $workspaceKey }}"
                       @checked(old('workspace_key') === $workspaceKey) required>
                <span>
                    <span class="fw-semibold d-block">{{ $workspace['tenant']['name'] ?? 'Organizacija' }}</span>
                    <span class="small text-muted">
                        {{ $application?->name ?? $workspace['application_slug'] }}
                        @if (! empty($workspace['tenant']['slug']))
                            · {{ $workspace['tenant']['slug'] }}
                        @endif
                    </span>
                </span>
            </label>
        @endforeach
    </div>

    @error('workspace_key')
        <div class="text-danger small mb-3">{{ $message }}</div>
    @enderror

    <div class="d-grid">
        <button type="submit" class="btn btn-dark">Nastavi</button>
    </div>
</form>
@endsection
