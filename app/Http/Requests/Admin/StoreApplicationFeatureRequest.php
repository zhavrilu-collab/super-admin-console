<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApplicationFeatureType;
use App\Services\Admin\AdminSaaSService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationFeatureRequest extends FormRequest
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
        $applicationId = app(AdminSaaSService::class)->getActiveApplicationId();

        return [
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('application_features', 'key')->where(
                    static fn ($query) => $query->where('application_id', $applicationId),
                ),
            ],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ApplicationFeatureType::class)],
            'unit' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();
        $type = $data['type'] instanceof ApplicationFeatureType
            ? $data['type']
            : ApplicationFeatureType::from((string) $data['type']);

        return [
            'key' => $data['key'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'unit' => $type === ApplicationFeatureType::Limit
                ? ($data['unit'] ?? null)
                : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
