<?php

namespace App\Http\Requests\Admin;

use App\Rules\ValidSubscriptionPlanSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(\App\Enums\TenantStatus::class)],
            'plan' => ['nullable', 'string', 'max:100', new ValidSubscriptionPlanSlug],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['name', 'slug', 'external_id', 'status', 'plan', 'created_at', 'synced_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    public function searchQuery(): ?string
    {
        $query = trim($this->string('q')->toString());

        return $query !== '' ? $query : null;
    }

    public function statusFilter(): ?\App\Enums\TenantStatus
    {
        return $this->enum('status', \App\Enums\TenantStatus::class);
    }

    public function planFilterSlug(): ?string
    {
        $plan = trim($this->string('plan')->toString());

        return $plan !== '' ? $plan : null;
    }

    public function sortColumn(): string
    {
        return $this->input('sort', 'name');
    }

    public function sortDirection(): string
    {
        return $this->input('direction', 'asc');
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 25);
    }

    public function hasActiveFilters(): bool
    {
        return $this->searchQuery() !== null
            || $this->statusFilter() !== null
            || $this->planFilterSlug() !== null
            || $this->filled('date_from')
            || $this->filled('date_to');
    }

    public function hasAnyActiveState(): bool
    {
        return $this->hasActiveFilters()
            || $this->sortColumn() !== 'name'
            || $this->sortDirection() !== 'asc'
            || $this->perPage() !== 25;
    }

    /**
     * @param  array<string, mixed|null>  $overrides
     * @return array<string, mixed>
     */
    public function queryParameters(array $overrides = []): array
    {
        $params = [
            'q' => $this->input('q'),
            'status' => $this->input('status'),
            'plan' => $this->input('plan'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'sort' => $this->input('sort', 'name'),
            'direction' => $this->input('direction', 'asc'),
            'per_page' => $this->input('per_page'),
            'page' => $this->input('page'),
        ];

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
            } else {
                $params[$key] = $value;
            }
        }

        if (($params['sort'] ?? 'name') === 'name' && ($params['direction'] ?? 'asc') === 'asc') {
            unset($params['sort'], $params['direction']);
        }

        if ((int) ($params['per_page'] ?? 25) === 25) {
            unset($params['per_page']);
        }

        return array_filter(
            $params,
            static fn ($value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * @param  array<string, mixed|null>  $overrides
     */
    public function dashboardUrl(array $overrides = []): string
    {
        return route('admin.dashboard', $this->queryParameters($overrides));
    }

    public function statusFilterUrl(string $status): string
    {
        if ($this->input('status') === $status) {
            return $this->dashboardUrl(['status' => null, 'page' => null]);
        }

        return $this->dashboardUrl(['status' => $status, 'page' => null]);
    }

    public function planFilterUrl(string $plan): string
    {
        if ($this->input('plan') === $plan) {
            return $this->dashboardUrl(['plan' => null, 'page' => null]);
        }

        return $this->dashboardUrl(['plan' => $plan, 'page' => null]);
    }

    public function sortUrl(string $column): string
    {
        $direction = 'asc';

        if ($this->sortColumn() === $column && $this->sortDirection() === 'asc') {
            $direction = 'desc';
        }

        return $this->dashboardUrl([
            'sort' => $column,
            'direction' => $direction,
            'page' => null,
        ]);
    }

    public function sortIndicator(string $column): string
    {
        if ($this->sortColumn() !== $column) {
            return '';
        }

        return $this->sortDirection() === 'asc' ? ' ↑' : ' ↓';
    }

    public function isStatusFilterActive(?string $status): bool
    {
        return $status === null
            ? $this->input('status') === null
            : $this->input('status') === $status;
    }

    public function isPlanFilterActive(string $plan): bool
    {
        return $this->input('plan') === $plan;
    }
}
