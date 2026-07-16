<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PlatformTwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required_without:recovery_code', 'nullable', 'string'],
            'recovery_code' => ['required_without:code', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('code') && ! $this->filled('recovery_code')) {
                $validator->errors()->add('code', 'Unesi kôd iz autentifikatora ili rezervni kôd.');
            }
        });
    }
}
