@extends('layouts.admin')

@section('title', 'Uredi aplikaciju')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Uredi: {{ $application->name }}</h1>
    <p class="text-muted mb-0">Ažurirajte meta-podatke i API vezu.</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.applications.update', $application) }}">
            @csrf
            @method('PATCH')
            @include('admin.applications._form', ['application' => $application])
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-dark">Spremi promjene</button>
                <a href="{{ route('admin.applications.index') }}" class="btn btn-outline-secondary">Odustani</a>
            </div>
        </form>
    </div>
</div>
@endsection
