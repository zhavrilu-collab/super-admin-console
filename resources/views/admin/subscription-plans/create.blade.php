@extends('layouts.admin')

@section('title', 'Novi paket')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Novi paket pretplate</h1>
    <p class="text-muted mb-0">{{ $activeApplication->name }}</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.subscription-plans.store') }}">
            @include('admin.subscription-plans._form', ['plan' => null, 'badgeOptions' => $badgeOptions])
            <button type="submit" class="btn btn-dark">Spremi paket</button>
            <a href="{{ route('admin.subscription-plans.index') }}" class="btn btn-outline-secondary">Odustani</a>
        </form>
    </div>
</div>
@endsection
