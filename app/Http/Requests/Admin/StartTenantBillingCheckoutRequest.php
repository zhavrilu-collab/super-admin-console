<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartTenantBillingCheckoutRequest extends FormRequest
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
        /** @var \App\Models\Tenant $tenant */
        $tenant = $this->route('tenant');

        return [
            'plan_slug' => [
                'required',
                'string',
                Rule::exists('subscription_plans', 'slug')->where(
                    static fn ($query) => $query->where('application_id', $tenant->application_id),
                ),
            ],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function planSlug(): string
    {
        return $this->string('plan_slug')->toString();
    }
}
