<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApplicationFeatureType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationFeatureRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
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
        /** @var \App\Models\ApplicationFeature $feature */
        $feature = $this->route('applicationFeature');

        return [
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'unit' => $feature->type === ApplicationFeatureType::Limit
                ? ($data['unit'] ?? null)
                : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
