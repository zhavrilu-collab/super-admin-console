<?php

namespace App\Http\Requests\Admin;

use App\Rules\ValidSubscriptionPlanSlug;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantPlanRequest extends FormRequest
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
            'plan' => ['required', 'string', 'max:100', new ValidSubscriptionPlanSlug],
        ];
    }

    public function validatedPlanSlug(): string
    {
        return $this->string('plan')->toString();
    }
}
