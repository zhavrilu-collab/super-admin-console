<?php

namespace App\Http\Requests\Admin;

use App\Enums\TenantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(TenantStatus::class)],
        ];
    }

    public function validatedStatus(): TenantStatus
    {
        /** @var TenantStatus $status */
        $status = $this->enum('status', TenantStatus::class);

        return $status;
    }
}
