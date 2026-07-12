<?php

namespace App\Http\Requests\Admin;

use App\Enums\TenantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateTenantStatusRequest extends FormRequest
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
            'tenant_ids' => ['required', 'array', 'min:1', 'max:50'],
            'tenant_ids.*' => ['integer', 'distinct'],
            'status' => ['required', Rule::enum(TenantStatus::class)],
            'redirect' => ['nullable', 'array'],
            'redirect.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function redirectQuery(): array
    {
        $redirect = $this->input('redirect', []);

        if (! is_array($redirect)) {
            return [];
        }

        return array_filter(
            $redirect,
            static fn ($value): bool => is_string($value) && $value !== '',
        );
    }

    /**
     * @return list<int>
     */
    public function tenantIds(): array
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $this->input('tenant_ids', []));

        return array_values(array_unique($ids));
    }

    public function validatedStatus(): TenantStatus
    {
        /** @var TenantStatus $status */
        $status = $this->enum('status', TenantStatus::class);

        return $status;
    }
}
