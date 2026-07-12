@extends('layouts.admin')

@section('title', 'Nova aplikacija')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Nova SaaS aplikacija</h1>
    <p class="text-muted mb-0">Dodajte novi proizvod u centralnu konzolu.</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.applications.store') }}">
            @include('admin.applications._form')
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-dark">Spremi aplikaciju</button>
                <a href="{{ route('admin.applications.index') }}" class="btn btn-outline-secondary">Odustani</a>
            </div>
        </form>
    </div>
</div>
@endsection
