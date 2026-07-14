<?php

namespace App\Http\Requests\Api;

use App\Models\PlatformInvite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformInviteRequest extends FormRequest
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
            'application_slug' => ['required', 'string', 'max:100'],
            'tenant_external_id' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in([PlatformInvite::ROLE_OWNER, PlatformInvite::ROLE_ADMIN])],
            'invited_by_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
