@extends('layouts.admin')

@section('title', 'Paketi pretplate')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Paketi pretplate</h1>
        <p class="text-muted mb-0">
            Aktivna aplikacija: <strong>{{ $activeApplication->name }}</strong>
        </p>
        <p class="text-muted small mb-0">
            <strong>Naziv</strong> je prikaz korisnicima (Osnovni, Standardni, Napredni).
            <strong>Slug</strong> je tehnički ključ (npr. <code>basic</code>, <code>standard</code>, <code>premium</code>).
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.application-features.index') }}" class="btn btn-outline-secondary">Značajke</a>
        <a href="{{ route('admin.subscription-plans.create') }}" class="btn btn-dark">Novi paket</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Naziv</th>
                    <th>Slug</th>
                    <th>Limit članova</th>
                    <th>Web / domene</th>
                    <th>Stripe Price</th>
                    <th>Mjesečno</th>
                    <th>Tenanata</th>
                    <th>Zadani</th>
                    <th class="text-end">Akcije</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                    <tr>
                        <td>
                            <span class="badge bg-{{ $plan->badge_class }}">{{ $plan->name }}</span>
                        </td>
                        <td><code>{{ $plan->slug }}</code></td>
                        <td>{{ $plan->memberLimitLabel() }}</td>
                        <td class="small text-muted">
                            @php $features = $plan->featuresMap(); @endphp
                            @if(! empty($features['subdomain'])) poddomena @endif
                            @if(! empty($features['custom_domain'])) vlastita domena @endif
                            @if(! empty($features['editable_sections'])) sekcije @endif
                            @if(! empty($features['cookie_banner'])) cookies @endif
                            @if(empty($features['subdomain']) && empty($features['custom_domain']) && empty($features['editable_sections']) && empty($features['cookie_banner']))
                                —
                            @endif
                        </td>
                        <td class="small">
                            @if($plan->stripe_price_id)
                                <code>{{ $plan->stripe_price_id }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small">{{ $plan->monthlyPriceLabel() }}</td>
                        <td>{{ $tenantCounts[$plan->slug] ?? 0 }}</td>
                        <td>
                            @if($plan->is_default)
                                <span class="badge bg-success">Da</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.subscription-plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">Uredi</a>
                            <form method="POST" action="{{ route('admin.subscription-plans.destroy', $plan) }}" class="d-inline js-confirm-action"
                                  data-confirm="Obrisati paket {{ $plan->name }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Obriši</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            Nema definiranih paketa. Dodajte prvi paket za ovu aplikaciju.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
