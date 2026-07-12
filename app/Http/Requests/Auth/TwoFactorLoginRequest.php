<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Support\TwoFactorSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TwoFactorLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session()->has(TwoFactorSession::LOGIN_USER_ID);
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

    public function passesTwoFactorChallenge(User $user, TwoFactorAuthenticationService $twoFactorAuthenticationService): bool
    {
        if ($this->filled('recovery_code')) {
            return $twoFactorAuthenticationService->verifyRecoveryCode(
                $user,
                $this->string('recovery_code')->toString(),
            );
        }

        if ($this->filled('code')) {
            return $twoFactorAuthenticationService->verifyForUser(
                $user,
                $this->string('code')->toString(),
            );
        }

        return false;
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
