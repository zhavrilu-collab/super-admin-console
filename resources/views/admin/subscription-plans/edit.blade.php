@extends('layouts.admin')

@section('title', 'Uredi paket')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Uredi paket pretplate</h1>
    <p class="text-muted mb-0">{{ $activeApplication->name }}</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.subscription-plans.update', $plan) }}">
            @csrf
            @method('PUT')
            @include('admin.subscription-plans._form', ['plan' => $plan, 'badgeOptions' => $badgeOptions])
            <button type="submit" class="btn btn-dark">Spremi promjene</button>
            <a href="{{ route('admin.subscription-plans.index') }}" class="btn btn-outline-secondary">Odustani</a>
        </form>
    </div>
</div>
@endsection
