<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateBillingPortalRequest extends FormRequest
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
            'return_url' => ['required', 'url', 'max:2048'],
        ];
    }
}
