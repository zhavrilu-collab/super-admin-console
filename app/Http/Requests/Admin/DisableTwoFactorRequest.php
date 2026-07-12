<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DisableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (config('security.require_super_admin_two_factor', true)) {
            return false;
        }

        return $this->user()?->isSuperAdmin() === true
            && $this->user()->hasTwoFactorEnabled();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ];
    }
}
