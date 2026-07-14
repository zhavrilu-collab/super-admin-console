<?php

namespace App\Http\Requests\Api;

use App\Enums\CustomerWebhookEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatchCustomerEventRequest extends FormRequest
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
            'event' => ['required', 'string', Rule::in(CustomerWebhookEvent::values())],
            'data' => ['required', 'array'],
        ];
    }
}
