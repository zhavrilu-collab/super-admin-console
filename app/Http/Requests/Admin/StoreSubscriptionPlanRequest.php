<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesSubscriptionPlanPayload;
use App\Services\Admin\AdminSaaSService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
{
    use ValidatesSubscriptionPlanPayload;

    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareFeatureBooleans();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $applicationId = app(AdminSaaSService::class)->getActiveApplicationId();

        return [
            ...$this->basePlanRules(),
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('subscription_plans', 'slug')->where(
                    static fn ($query) => $query->where('application_id', $applicationId),
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        return $this->validatedPlanPayload();
    }
}
