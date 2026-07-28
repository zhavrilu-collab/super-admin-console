<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSsoRequest extends FormRequest
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
            'sso_enforced' => ['required', 'boolean'],
        ];
    }

    public function ssoEnforced(): bool
    {
        return $this->boolean('sso_enforced');
    }
}
