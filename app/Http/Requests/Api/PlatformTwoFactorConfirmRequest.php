<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PlatformTwoFactorConfirmRequest extends FormRequest
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
            'code' => ['required', 'string', 'digits:6'],
            'secret' => ['required', 'string'],
        ];
    }
}
