<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ExtendTenantTrialRequest extends FormRequest
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
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'days.required' => 'Unesite broj dana za produljenje.',
            'days.integer' => 'Broj dana mora biti cijeli broj.',
            'days.min' => 'Probni period mora biti produljen za najmanje 1 dan.',
            'days.max' => 'Probni period se može produljiti najviše za 365 dana.',
        ];
    }

    public function days(): int
    {
        return (int) $this->validated('days');
    }
}
