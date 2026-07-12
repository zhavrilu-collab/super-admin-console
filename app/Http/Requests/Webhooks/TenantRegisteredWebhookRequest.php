<?php

namespace App\Http\Requests\Webhooks;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRegisteredWebhookRequest extends FormRequest
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
        $applicationId = Application::query()
            ->where('slug', $this->input('application_slug'))
            ->value('id');

        return [
            'application_slug' => ['required', 'string', 'max:100', Rule::exists('applications', 'slug')],
            'organization' => ['required', 'array'],
            'organization.id' => ['required'],
            'organization.name' => ['required', 'string', 'max:255'],
            'organization.slug' => ['required', 'string', 'max:255'],
            'organization.status' => ['required', 'string', 'max:50'],
            'organization.plan' => [
                'required',
                'string',
                'max:100',
                Rule::exists('subscription_plans', 'slug')->where(
                    static fn ($query) => $query->where('application_id', $applicationId),
                ),
            ],
            'organization.email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
