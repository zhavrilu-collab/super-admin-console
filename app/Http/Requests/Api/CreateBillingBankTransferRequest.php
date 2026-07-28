<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateBillingBankTransferRequest extends FormRequest
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
            'plan_slug' => ['required', 'string', 'max:100'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'payer_oib' => ['nullable', 'string', 'max:11'],
            'payer_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
