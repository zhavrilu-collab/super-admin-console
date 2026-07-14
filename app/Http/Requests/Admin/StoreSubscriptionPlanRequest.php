<?php

namespace App\Http\Requests\Admin;

use App\Models\SubscriptionPlan;
use App\Services\Admin\AdminSaaSService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
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
        $applicationId = app(AdminSaaSService::class)->getActiveApplicationId();

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('subscription_plans', 'slug')->where(
                    static fn ($query) => $query->where('application_id', $applicationId),
                ),
            ],
            'member_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'badge_class' => ['required', Rule::in(['secondary', 'primary', 'dark', 'success', 'warning', 'danger', 'info'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_default' => ['sometimes', 'boolean'],
            'subdomain' => ['sometimes', 'boolean'],
            'custom_domain' => ['sometimes', 'boolean'],
            'editable_sections' => ['sometimes', 'boolean'],
            'cookie_banner' => ['sometimes', 'boolean'],
            'stripe_product_id' => ['nullable', 'string', 'max:255'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'monthly_price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'member_limit' => $this->filled('member_limit') ? (int) $data['member_limit'] : null,
            'badge_class' => $data['badge_class'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'subdomain' => (bool) ($data['subdomain'] ?? false),
            'custom_domain' => (bool) ($data['custom_domain'] ?? false),
            'editable_sections' => (bool) ($data['editable_sections'] ?? false),
            'cookie_banner' => (bool) ($data['cookie_banner'] ?? false),
            'stripe_product_id' => $this->filled('stripe_product_id') ? $data['stripe_product_id'] : null,
            'stripe_price_id' => $this->filled('stripe_price_id') ? $data['stripe_price_id'] : null,
            'monthly_price_cents' => $this->monthlyPriceCentsFromInput($data),
        ];
    }

    private function monthlyPriceCentsFromInput(array $data): ?int
    {
        if (! $this->filled('monthly_price')) {
            return null;
        }

        return (int) round(((float) $data['monthly_price']) * 100);
    }
}
