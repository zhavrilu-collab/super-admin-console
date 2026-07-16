<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PlatformTwoFactorDisableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformUser() === true;
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
