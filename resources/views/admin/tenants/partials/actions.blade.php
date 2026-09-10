<div class="btn-group">
    <button type="button"
            class="btn btn-sm btn-outline-secondary dropdown-toggle"
            data-bs-toggle="dropdown"
            data-bs-popper-config='{"strategy":"fixed"}'
            aria-expanded="false">
        Akcije
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><h6 class="dropdown-header">Status</h6></li>
        @if($tenant->status !== \App\Enums\TenantStatus::Active)
            <li>
                <form method="POST" action="{{ route('admin.tenants.update-status', $tenant) }}" class="js-confirm-action" data-confirm="Odobriti tenant {{ $tenant->name }}?">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="dropdown-item text-success">Odobri</button>
                </form>
            </li>
        @endif
        @if($tenant->status !== \App\Enums\TenantStatus::Suspended)
            <li>
                <form method="POST" action="{{ route('admin.tenants.update-status', $tenant) }}" class="js-confirm-action" data-confirm="Suspendirati tenant {{ $tenant->name }}?">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="suspended">
                    <button type="submit" class="dropdown-item text-danger">Suspendiraj</button>
                </form>
            </li>
        @endif
        @if($tenant->status !== \App\Enums\TenantStatus::Pending)
            <li>
                <form method="POST" action="{{ route('admin.tenants.update-status', $tenant) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="pending">
                    <button type="submit" class="dropdown-item">Vrati na čekanje</button>
                </form>
            </li>
        @endif
        <li><hr class="dropdown-divider"></li>
        <li><h6 class="dropdown-header">Plan pretplate</h6></li>
        @foreach($subscriptionPlans ?? [] as $planOption)
            @if($tenant->plan !== $planOption->slug)
                <li>
                    <form method="POST" action="{{ route('admin.tenants.update-plan', $tenant) }}" class="js-tenant-plan-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="plan" value="{{ $planOption->slug }}">
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                            <span>Postavi</span>
                            <span class="badge bg-{{ $planOption->badge_class }}">{{ $planOption->name }}</span>
                        </button>
                    </form>
                </li>
            @endif
        @endforeach
        @if(collect($subscriptionPlans ?? [])->where(fn ($plan) => $plan->slug !== $tenant->plan)->isEmpty())
            <li><span class="dropdown-item-text text-muted small">Već na aktivnom paketu</span></li>
        @endif
        @if($tenant->status === \App\Enums\TenantStatus::Active && $tenant->application?->api_base_url)
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('admin.tenants.impersonate', $tenant) }}" class="js-confirm-action" data-confirm="Ući u tenant {{ $tenant->name }} kao support?">
                    @csrf
                    <button type="submit" class="dropdown-item">Uđi kao tenant</button>
                </form>
            </li>
        @endif
        <li><hr class="dropdown-divider"></li>
        <li>
            <form method="POST"
                  action="{{ route('admin.tenants.destroy', $tenant) }}"
                  class="js-confirm-action"
                  data-confirm="Trajno obrisati tenant {{ $tenant->name }} ({{ $tenant->slug }})? Ovo briše podatke u konzoli i u SaaS aplikaciji.">
                @csrf
                @method('DELETE')
                <button type="submit" class="dropdown-item text-danger">Obriši tenant</button>
            </form>
        </li>
    </ul>
</div>
