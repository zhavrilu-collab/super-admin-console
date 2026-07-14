<?php

namespace App\Http\Requests\Api;

use App\Models\PlatformTenantMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPlatformMembershipsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_slug' => ['required', 'string', 'max:64'],
            'memberships' => ['required', 'array'],
            'memberships.*.external_user_id' => ['required', 'string', 'max:64'],
            'memberships.*.tenant_external_id' => ['required', 'string', 'max:64'],
            'memberships.*.role' => ['required', 'string', Rule::in([
                PlatformTenantMembership::ROLE_OWNER,
                PlatformTenantMembership::ROLE_ADMIN,
            ])],
        ];
    }
}
